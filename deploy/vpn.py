#!/usr/bin/env python3
"""
Сервер VPN (Xray в /opt/vpn, контейнер vpn-xray): входы и ссылки для приложения.

Запускать на сервере из клона репозитория:
    python3 /opt/starter/starter-backend/deploy/vpn.py <команда> …

Команды:
    list                                   входы: порт, транспорт, маскировка (без ключей)
    add TAG --port N --network tcp|xhttp [--sni HOST] [--target HOST:443] [--dry-run]
                                           новый вход VLESS + Reality для всех ключей users.json
    set TAG [--port N] [--sni HOST] [--target HOST:443] [--dry-run]
                                           поменять порт или маскировку входа
    remove TAG [--dry-run]                 удалить вход (и правило ufw)
    check-sni HOST                         годится ли сайт для маскировки Reality (TLS 1.3, H2)
    link TAG [--user NAME]                 ссылка vless:// для приложения — печатает ключ, только в pipe:
        python3 vpn.py link tcp-8443 | docker compose exec -T php php artisan vpn:profile:add "Запасной" --stdin

Каждое изменение: проверка нового конфига `xray run -test` тем же образом, что у vpn-xray (не
прошла — ничего не меняется), бэкап в /opt/vpn/backup-<время>/, правило ufw, перезапуск vpn-xray
(подключённые теряют связь на 1–2 секунды). Если Xray после перезапуска не поднялся, конфиг и ufw
откатываются и контейнер перезапускается со старым конфигом. --dry-run только проверяет. Два запуска
одновременно не пройдут: второй ждёт блокировку.

keys.json: host (адрес сервера в ссылках), private_key, public_key, short_id — ключ Reality общий для
всех входов, которые заводит скрипт (вход с собственным ключом, добавленный руками, `link` не соберёт).
users.json: {"имя": {"id": "uuid"}}; каждый вход получает всех пользователей. Контейнер vpn-xray
работает в сети хоста (network_mode: host) — иначе новые порты не были бы видны снаружи.
"""

import argparse
import fcntl
import json
import os
import secrets
import shutil
import subprocess
import sys
import time
import urllib.parse
from pathlib import Path

VPN_DIR = Path('/opt/vpn')
CONFIG = VPN_DIR / 'config.json'
KEYS = VPN_DIR / 'keys.json'
USERS = VPN_DIR / 'users.json'
CONTAINER = 'vpn-xray'
LOCK = VPN_DIR / '.vpn.lock'
APP_USER = 'kolyansburg-app'
DEFAULT_SNI = 'www.samsung.com'
VISION = 'xtls-rprx-vision'


def fail(message: str) -> None:
    print(f'Ошибка: {message}', file=sys.stderr)
    sys.exit(1)


def load(path: Path) -> dict:
    return json.loads(path.read_text())


def user_ids(users: dict) -> dict[str, str]:
    """users.json: {"имя": {"id": "uuid"}} (старые записи — просто строка с UUID)"""
    return {name: value['id'] if isinstance(value, dict) else value for name, value in users.items()}


def inbound_by_tag(config: dict, tag: str) -> dict:
    for inbound in config.get('inbounds', []):
        if inbound.get('tag') == tag:
            return inbound
    fail(f'входа «{tag}» нет; список — python3 vpn.py list')
    raise AssertionError


def describe(inbound: dict) -> str:
    stream = inbound.get('streamSettings', {})
    reality = stream.get('realitySettings', {})
    names = ','.join(reality.get('serverNames', [])) or '—'
    clients = len(inbound.get('settings', {}).get('clients', []))
    return (f"{inbound.get('tag', '?'):<14} порт {inbound.get('port', '?'):<6} "
            f"{inbound.get('protocol', '?')}/{stream.get('network', 'tcp')}/{stream.get('security', 'none'):<8} "
            f"маскировка {names:<24} ключей {clients}")


def run(cmd: list[str], check: bool = True) -> subprocess.CompletedProcess:
    return subprocess.run(cmd, capture_output=True, text=True, check=check)


def write_atomic(path: Path, text: str, mode: int) -> None:
    """Через временный файл и rename: обрыв посреди записи не оставит обрезанный конфиг"""
    tmp = path.with_name(f'.{path.name}.tmp')
    tmp.write_text(text)
    tmp.chmod(mode)
    os.replace(tmp, path)


def container_image() -> str:
    """Образ, на котором реально работает vpn-xray: проверять конфиг надо им, а не свежим latest"""
    result = run(['docker', 'inspect', '-f', '{{.Image}}', CONTAINER], check=False)
    if result.returncode != 0:
        fail(f'контейнера {CONTAINER} нет — сервер VPN не запущен')
    return result.stdout.strip()


def require_host_network() -> None:
    mode = run(['docker', 'inspect', '-f', '{{.HostConfig.NetworkMode}}', CONTAINER]).stdout.strip()
    if mode != 'host':
        fail(f'{CONTAINER} не в сети хоста ({mode}): новые порты не будут видны снаружи')


def test_config(config: dict) -> None:
    """xray run -test на копии; ключи не печатаем"""
    candidate = VPN_DIR / 'config.candidate.json'
    # 0644, как у config.json: Xray в контейнере не root. Посторонним файл не виден — /opt/vpn только для root
    write_atomic(candidate, json.dumps(config, indent=2), 0o644)
    try:
        result = run(['docker', 'run', '--rm', '-v', f'{candidate}:/c.json:ro', container_image(),
                      'run', '-test', '-c', '/c.json'], check=False)
    finally:
        candidate.unlink(missing_ok=True)
    if 'Configuration OK' not in result.stdout + result.stderr:
        lines = [line for line in (result.stdout + result.stderr).splitlines() if 'REALITY: Listening' not in line]
        fail('xray не принял конфиг, ничего не изменено:\n' + '\n'.join(lines[-5:]))


def xray_healthy(since: str) -> bool:
    """Контейнер работает, не перезапускается по кругу и Xray написал «started» после перезапуска"""
    time.sleep(3)
    state = run(['docker', 'inspect', '-f', '{{.State.Running}} {{.State.Restarting}} {{.RestartCount}}', CONTAINER],
                check=False).stdout.split()
    if state[:2] != ['true', 'false']:
        return False
    restarts = state[2] if len(state) > 2 else '0'
    time.sleep(2)
    again = run(['docker', 'inspect', '-f', '{{.RestartCount}}', CONTAINER], check=False).stdout.strip()
    logs = run(['docker', 'logs', '--since', since, CONTAINER], check=False)
    return again == restarts and 'started' in logs.stdout + logs.stderr


def ufw(args: list[str]) -> None:
    result = run(['ufw', *args], check=False)
    if result.returncode != 0:
        print(f'Внимание: ufw {" ".join(args)} — {result.stderr.strip() or result.stdout.strip()}', file=sys.stderr)


def apply(config: dict, dry_run: bool, open_ports: list[tuple[int, str]] = (), close_ports: list[int] = ()) -> None:
    test_config(config)
    if dry_run:
        print('Проверка пройдена (--dry-run: ничего не изменено)')
        return
    backup = VPN_DIR / f"backup-{time.strftime('%Y%m%d-%H%M%S')}-{secrets.token_hex(2)}"
    backup.mkdir(mode=0o700)
    for path in (CONFIG, USERS, KEYS):
        shutil.copy2(path, backup / path.name)
    previous = CONFIG.read_text()

    write_atomic(CONFIG, json.dumps(config, indent=2), 0o644)
    for port, comment in open_ports:
        ufw(['allow', f'{port}/tcp', 'comment', comment])
    for port in close_ports:
        ufw(['delete', 'allow', f'{port}/tcp'])
    since = time.strftime('%Y-%m-%dT%H:%M:%S')
    run(['docker', 'restart', CONTAINER], check=False)
    if xray_healthy(since):
        print(f'Готово. Бэкап прошлого конфига: {backup}')
        return

    # Не поднялся — возвращаем как было, чтобы VPN не лежал у всех
    write_atomic(CONFIG, previous, 0o644)
    for port, _ in open_ports:
        ufw(['delete', 'allow', f'{port}/tcp'])
    for port in close_ports:
        ufw(['allow', f'{port}/tcp', 'comment', 'vpn'])
    since = time.strftime('%Y-%m-%dT%H:%M:%S')
    run(['docker', 'restart', CONTAINER], check=False)
    restored = xray_healthy(since)
    fail(f'Xray не поднялся с новым конфигом; вернул прежний ({"работает" if restored else "ПРОВЕРЬТЕ: docker logs " + CONTAINER}). '
         f'Бэкап: {backup}')


def used_ports(config: dict, exclude_tag: str | None = None) -> set[int]:
    return {i.get('port') for i in config.get('inbounds', []) if i.get('tag') != exclude_tag}


def check_port_free(config: dict, port: int, tag: str | None = None) -> None:
    if not 1 <= port <= 65535:
        fail(f'порт {port} вне 1–65535')
    require_host_network()
    if port in used_ports(config, tag):
        fail(f'порт {port} уже занят другим входом')
    if port in (22, 80, 443, 3478):
        fail(f'порт {port} занят мессенджером или SSH')
    busy = run(['ss', '-ltnH', f'sport = :{port}'], check=False).stdout.strip()
    if busy and port not in used_ports(config):
        fail(f'порт {port} уже слушает другой процесс')


def reality_settings(sni: str, target: str | None, keys: dict) -> dict:
    return {
        'show': False,
        'target': target or f'{sni}:443',
        'serverNames': [sni],
        'privateKey': keys['private_key'],
        'shortIds': [keys['short_id']],
    }


def cmd_list(_: argparse.Namespace) -> None:
    for inbound in load(CONFIG).get('inbounds', []):
        print(describe(inbound))


def cmd_add(args: argparse.Namespace) -> None:
    config, keys, users = load(CONFIG), load(KEYS), user_ids(load(USERS))
    if any(i.get('tag') == args.tag for i in config.get('inbounds', [])):
        fail(f'вход «{args.tag}» уже есть; поменять — python3 vpn.py set {args.tag} …')
    check_port_free(config, args.port)
    clients = []
    for name, uid in users.items():
        client = {'id': uid, 'email': f'{name}@{args.tag}'}
        if args.network == 'tcp':
            client['flow'] = VISION
        clients.append(client)
    stream = {
        'network': args.network,
        'security': 'reality',
        'realitySettings': reality_settings(args.sni, args.target, keys),
    }
    if args.network == 'xhttp':
        stream['xhttpSettings'] = {'path': '/' + secrets.token_urlsafe(9), 'mode': 'auto'}
    config['inbounds'].append({
        'tag': args.tag,
        'listen': '0.0.0.0',
        'port': args.port,
        'protocol': 'vless',
        'settings': {'clients': clients, 'decryption': 'none'},
        'streamSettings': stream,
        'sniffing': {'enabled': True, 'destOverride': ['http', 'tls', 'quic']},
    })
    apply(config, args.dry_run, open_ports=[(args.port, f'vpn {args.tag}')])
    if not args.dry_run:
        print(describe(config['inbounds'][-1]))
        print(f'Ссылка для приложения: python3 {sys.argv[0]} link {args.tag} | … vpn:profile:add "Название" --stdin')


def cmd_set(args: argparse.Namespace) -> None:
    config = load(CONFIG)
    inbound = inbound_by_tag(config, args.tag)
    if args.port is None and args.sni is None and args.target is None:
        fail('нечего менять: --port, --sni или --target')
    open_ports, close_ports = [], []
    if args.port is not None and args.port != inbound['port']:
        check_port_free(config, args.port, args.tag)
        close_ports.append(inbound['port'])
        open_ports.append((args.port, f'vpn {args.tag}'))
        inbound['port'] = args.port
    reality = inbound.get('streamSettings', {}).get('realitySettings')
    if args.sni is not None or args.target is not None:
        if reality is None:
            fail('у этого входа нет Reality — маскировку менять негде')
        old_sni = reality['serverNames'][0]
        old_target = reality.get('target') or reality.get('dest')
        sni = args.sni or old_sni
        reality['serverNames'] = [sni]
        # Свой target (не «sni:443») без --target не трогаем — меняется только при смене обычного
        if args.target is not None:
            reality['target'] = args.target
        elif old_target in (None, f'{old_sni}:443'):
            reality['target'] = f'{sni}:443'
        else:
            reality['target'] = old_target
        reality.pop('dest', None)
    apply(config, args.dry_run, open_ports, close_ports)
    if not args.dry_run:
        print(describe(inbound))
        print('Ссылки на этот вход в приложении теперь старые: vpn:profile:edit ID --uri --stdin')


def cmd_remove(args: argparse.Namespace) -> None:
    config = load(CONFIG)
    inbound = inbound_by_tag(config, args.tag)
    config['inbounds'].remove(inbound)
    if not config['inbounds']:
        fail('это последний вход — без него VPN не работает')
    apply(config, args.dry_run, close_ports=[inbound['port']])


def cmd_check_sni(args: argparse.Namespace) -> None:
    result = run(['docker', 'run', '--rm', container_image(), 'tls', 'ping', args.host], check=False)
    output = result.stdout + result.stderr
    print(output.strip())
    print('\nДля Reality сайт должен открываться с сервера по TLS 1.3 с HTTP/2 и не быть заблокирован'
          ' в России; сертификат выше выдан на этот домен — значит, сервер до него достучался.')


def cmd_link(args: argparse.Namespace) -> None:
    config, keys, users = load(CONFIG), load(KEYS), user_ids(load(USERS))
    inbound = inbound_by_tag(config, args.tag)
    if args.user not in users:
        fail(f'ключа «{args.user}» нет в users.json')
    if inbound.get('protocol') != 'vless':
        fail('ссылку собираю только для входов VLESS')
    stream = inbound.get('streamSettings', {})
    network = stream.get('network', 'tcp')
    query = {'type': network, 'security': stream.get('security', 'none'), 'fp': 'chrome'}
    reality = stream.get('realitySettings')
    if reality is not None:
        if reality.get('privateKey') != keys['private_key']:
            fail('у этого входа свой ключ Reality, не из keys.json — ссылку собирать руками')
        query.update(sni=reality['serverNames'][0], pbk=keys['public_key'], sid=reality['shortIds'][0])
    if network == 'tcp' and any(c.get('flow') == VISION for c in inbound['settings']['clients']):
        query['flow'] = VISION
    if network == 'xhttp':
        xhttp = stream.get('xhttpSettings', {})
        query.update(path=xhttp.get('path', '/'), mode=xhttp.get('mode', 'auto'))
    if sys.stdout.isatty():
        print('Ссылка содержит ключ — передавайте её через pipe, а не копируйте с экрана.', file=sys.stderr)
    print(f"vless://{users[args.user]}@{keys['host']}:{inbound['port']}?{urllib.parse.urlencode(query)}#{args.tag}")


def main() -> None:
    parser = argparse.ArgumentParser(description='Входы сервера VPN и ссылки для приложения')
    sub = parser.add_subparsers(dest='command', required=True)

    sub.add_parser('list').set_defaults(func=cmd_list)

    add = sub.add_parser('add')
    add.add_argument('tag')
    add.add_argument('--port', type=int, required=True)
    add.add_argument('--network', choices=['tcp', 'xhttp'], default='tcp')
    add.add_argument('--sni', default=DEFAULT_SNI)
    add.add_argument('--target')
    add.add_argument('--dry-run', action='store_true')
    add.set_defaults(func=cmd_add)

    change = sub.add_parser('set')
    change.add_argument('tag')
    change.add_argument('--port', type=int)
    change.add_argument('--sni')
    change.add_argument('--target')
    change.add_argument('--dry-run', action='store_true')
    change.set_defaults(func=cmd_set)

    remove = sub.add_parser('remove')
    remove.add_argument('tag')
    remove.add_argument('--dry-run', action='store_true')
    remove.set_defaults(func=cmd_remove)

    check = sub.add_parser('check-sni')
    check.add_argument('host')
    check.set_defaults(func=cmd_check_sni)

    link = sub.add_parser('link')
    link.add_argument('tag')
    link.add_argument('--user', default=APP_USER)
    link.set_defaults(func=cmd_link)

    args = parser.parse_args()
    if not CONFIG.exists():
        fail(f'нет {CONFIG}: запускать на сервере VPN')
    # Два изменения одновременно перетёрли бы конфиг друг друга
    with open(LOCK, 'w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        args.func(args)


if __name__ == '__main__':
    main()
