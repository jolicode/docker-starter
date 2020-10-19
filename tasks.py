from invoke import task
from shlex import quote
from colorama import Fore
import json
import os
import re
import requests
import subprocess


@task
def build(c):
    """
    Build the infrastructure
    """
    command = 'build'
    command += ' --build-arg PROJECT_NAME=%s' % c.project_name
    command += ' --build-arg USER_ID=%s' % c.user_id
    command += ' --build-arg PHP_VERSION=%s' % c.php_version

    with Builder(c):
        for service in c.services_to_build_first:
            docker_compose(c, '%s %s' % (command, service))

        docker_compose(c, command)


@task
def up(c):
    """
    Build and start the infrastructure
    """
    build(c)

    docker_compose(c, 'up --remove-orphans --detach')


@task
def start(c):
    """
    Build and start the infrastructure, then install the application (composer, yarn, ...)
    """
    if c.dinghy:
        machine_running = c.run('dinghy status', hide=True).stdout
        if machine_running.splitlines()[0].strip() != 'VM: running':
            c.run('dinghy up --no-proxy')
            c.run('docker-machine ssh dinghy "echo \'nameserver 8.8.8.8\' | sudo tee -a /etc/resolv.conf && sudo /etc/init.d/docker restart"')

    stop_workers(c)
    up(c)
    cache_clear(c)
    install(c)
    migrate(c)
    start_workers(c)

    print(Fore.GREEN + 'The stack is now up and running.')
    help(c)


@task
def install(c):
    """
    Install the application (composer, yarn, ...)
    """
    with Builder(c):
        if os.path.isfile(c.root_dir + '/' + c.project_directory + '/composer.json'):
            docker_compose_run(c, 'composer install -n --prefer-dist --optimize-autoloader', no_deps=True)
        if os.path.isfile(c.root_dir + '/' + c.project_directory + '/yarn.lock'):
            run_in_docker_or_locally_for_dinghy(c, 'yarn', no_deps=True)
        elif os.path.isfile(c.root_dir + '/' + c.project_directory + '/package.json'):
            run_in_docker_or_locally_for_dinghy(c, 'npm install', no_deps=True)


@task
def cache_clear(c):
    """
    Clear the application cache
    """
    # with Builder(c):
    #     docker_compose_run(c, 'rm -rf var/cache/ && php bin/console cache:warmup', no_deps=True)


@task
def migrate(c):
    """
    Migrate database schema
    """
    # with Builder(c):
    #     docker_compose_run(c, 'php bin/console doctrine:database:create --if-not-exists')
    #     docker_compose_run(c, 'php bin/console doctrine:migration:migrate -n --allow-no-migration')


@task
def builder(c, user="app"):
    """
    Open a shell (bash) into a builder container
    """
    with Builder(c):
        docker_compose_run(c, 'bash', user=user, bare_run=True)


@task
def logs(c):
    """
    Display infrastructure logs
    """
    docker_compose(c, 'logs -f --tail=150')


@task
def ps(c):
    """
    List containers status
    """
    docker_compose(c, 'ps --all')


@task
def stop(c):
    """
    Stop the infrastructure
    """
    docker_compose(c, 'stop')


@task
def start_workers(c):
    """
    Start the workers
    """
    workers = get_workers(c)

    if (len(workers) == 0):
        return

    c.start_workers = True
    c.run('docker update --restart=unless-stopped %s' % (' '.join(workers)), hide='both')
    docker_compose(c, 'up --remove-orphans --detach')


@task
def stop_workers(c):
    """
    Stop the workers
    """
    workers = get_workers(c)

    if (len(workers) == 0):
        return

    c.start_workers = False
    c.run('docker update --restart=no %s' % (' '.join(workers)), hide='both')
    c.run('docker stop %s' % (' '.join(workers)), hide='both')


@task
def destroy(c, force=False):
    """
    Clean the infrastructure (remove container, volume, networks)
    """

    if not force:
        ok = confirm_choice('Are you sure? This will permanently remove all containers, volumes, networks... created for this project.')
        if not ok:
            return

    with Builder(c):
        docker_compose(c, 'down --remove-orphans --volumes --rmi=local')


@task(default=True)
def help(c):
    """
    Display some help and available urls for the current project
    """

    print('Run ' + Fore.GREEN + 'inv help' + Fore.RESET + ' to display this help.')
    print('')

    print('Run ' + Fore.GREEN + 'inv --help' + Fore.RESET + ' to display invoke help.')
    print('')

    print('Run ' + Fore.GREEN + 'inv -l' + Fore.RESET + ' to list all the available tasks.')
    c.run('inv --list')

    print(Fore.GREEN + 'Available URLs for this project:' + Fore.RESET)
    for domain in [c.root_domain] + c.extra_domains:
        print("* " + Fore.YELLOW + "https://" + domain + Fore.RESET)

    try:
        response = json.loads(requests.get('http://%s:8080/api/http/routers' % (c.root_domain)).text)
        gen = (router for router in response if re.match("^%s-(.*)@docker$" % (c.project_name), router['name']))
        for router in gen:
            if router['service'] != 'frontend-%s' % (c.project_name):
                host = re.search('Host\(\`(?P<host>.*)\`\)', router['rule']).group('host')
                if host:
                    scheme = 'https' if 'https' in router['using'] else router['using'][0]
                    print("* " + Fore.YELLOW + scheme + "://" + host + Fore.RESET)
        print('')
    except:
        pass


def run_in_docker_or_locally_for_dinghy(c, command, no_deps=False):
    """
    Mac users have a lot of problems running Yarn / Webpack on the Docker stack so this func allow them to run these tools on their host
    """
    if c.dinghy:
        with c.cd(c.project_directory):
            c.run(command)
    else:
        docker_compose_run(c, command, no_deps=no_deps)


def docker_compose_run(c, command_name, service="builder", user="app", no_deps=False, workdir=None, port_mapping=False, bare_run=False):
    args = [
        'run',
        '--rm',
        '-u %s' % quote(user),
    ]

    if no_deps:
        args.append('--no-deps')

    if port_mapping:
        args.append('--service-ports')

    if workdir is not None:
        args.append('-w %s' % quote(workdir))

    docker_compose(c, '%s %s /bin/sh -c "exec %s"' % (
        ' '.join(args),
        quote(service),
        command_name
    ), bare_run=bare_run)


def docker_compose(c, command_name, bare_run=False):
    domains = '`' + '`, `'.join([c.root_domain] + c.extra_domains) + '`'

    # This list should be in sync with the one in invoke.py
    env = {
        'PROJECT_NAME': c.project_name,
        'PROJECT_DIRECTORY': c.project_directory,
        'PROJECT_ROOT_DOMAIN': c.root_domain,
        'PROJECT_DOMAINS': domains,
        'PROJECT_START_WORKERS': str(c.start_workers),
        'COMPOSER_CACHE_DIR': c.composer_cache_dir,
        'PHP_VERSION': c.php_version,
    }

    cmd = 'docker-compose -p %s %s %s' % (
        c.project_name,
        ' '.join('-f "' + c.root_dir + '/infrastructure/docker/' + file + '"' for file in c.docker_compose_files),
        command_name
    )

    # bare_run bypass invoke run() function
    # see https://github.com/pyinvoke/invoke/issues/744
    # Use it ONLY for task where you need to interact with the container like builder
    if (bare_run):
        env.update(os.environ)
        subprocess.run(cmd, shell=True, env=env)
    else:
        c.run(cmd, pty=not c.power_shell, env=env)


def get_workers(c):
    """
    Find worker containers for the current project
    """
    cmd = c.run('docker ps -a --filter "label=docker-starter.worker.%s" --quiet' % c.project_name, hide='both')
    return list(filter(None, cmd.stdout.rsplit("\n")))


def confirm_choice(message):
    confirm = input('%s [y]es or [N]o: ' % message)

    return re.compile('^y').search(confirm)


class Builder:
    def __init__(self, c):
        self.c = c

    def __enter__(self):
        self.docker_compose_files = self.c.docker_compose_files
        self.c.docker_compose_files = ['docker-compose.builder.yml'] + self.docker_compose_files

    def __exit__(self, type, value, traceback):
        self.c.docker_compose_files = self.docker_compose_files
