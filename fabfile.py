from fabric.api import task, env, shell_env
from fabric.operations import local, _shell_escape, settings
from functools import wraps
from fabric.context_managers import quiet
from fabric.colors import green, yellow
import os
import re
from sys import platform


# This will be used to prefix all docker objects (network, images, containers)
env.project_name = 'app'
# This is the root domain where the app will be available
# The "frontend" container will receive all the traffic
env.root_domain = env.project_name + '.test'
# This contains extra domains where the app will be available
# The "frontend" container will receive all the traffic
env.extra_domains = []
# This is the host directory containing your PHP application
env.project_directory = 'application'

services_to_build_first = [
    'php-base',
    'builder',
]


def with_builder(func):
    @wraps(func)
    def decorated(*args, **kwargs):
        compose_files = env.compose_files[:]
        env.compose_files = ['docker-compose.builder.yml'] + env.compose_files
        ret = func(*args, **kwargs)
        env.compose_files = compose_files

        return ret
    return decorated


@with_builder
def build():
    """
    Build the infrastructure
    """
    command = 'build'
    command += ' --build-arg PROJECT_NAME=%s' % env.project_name
    command += ' --build-arg USER_ID=%s' % env.user_id

    for service in services_to_build_first:
        commandForService = '%s %s' % (command, service)
        docker_compose(commandForService)

    docker_compose(command)


@task
def up():
    """
    Build and start the infrastructure
    """
    build()
    docker_compose('up --remove-orphans -d')


@task
def start():
    """
    Build and start the infrastructure, then install the application (composer, yarn, ...)
    """
    if env.dinghy:
        machine_running = local('dinghy status', capture=True)
        if machine_running.splitlines()[0].strip() != 'VM: running':
            local('dinghy up --no-proxy')
            local('docker-machine ssh dinghy "echo \'nameserver 8.8.8.8\' | sudo tee -a /etc/resolv.conf && sudo /etc/init.d/docker restart"')

    up()
    cache_clear()
    install()
    migrate()

    print green('You can now browse:')
    for domain in [env.root_domain] + env.extra_domains:
        print yellow("* https://" + domain)


@task
@with_builder
def install():
    """
    Install the application (composer, yarn, ...)
    """
    docker_compose_run('composer install -n --prefer-dist --optimize-autoloader')
    # run_in_docker_or_locally_for_dinghy('yarn')


@task
@with_builder
def cache_clear():
    """
    Clear the application cache
    """
    docker_compose_run('rm -rf var/cache/ && php bin/console cache:warmup', no_deps=True)


@task
@with_builder
def migrate():
    """
    Migrate database schema
    """
    docker_compose_run('php bin/console doctrine:database:create --if-not-exists', no_deps=True)
    docker_compose_run('php bin/console doctrine:migration:migrate -n', no_deps=True)


@task
@with_builder
def builder():
    """
    Open a shell (bash) into a builder container
    """
    docker_compose_run('bash')


@task
def logs():
    """
    Display infrastructure logs
    """
    docker_compose('logs -f --tail=150')


@task
def ps():
    """
    List containers status
    """
    docker_compose('ps')


@task
def stop():
    """
    Stop the infrastructure
    """
    docker_compose('stop')


@task
@with_builder
def destroy():
    """
    Clean the infrastructure (remove container, volume, networks)
    """
    docker_compose('down --volumes --rmi=local')


def run_in_docker_or_locally_for_dinghy(command):
    """
    Mac users have a lot of problems running Yarn / Webpack on the Docker stack so this func allow them to run these tools on their host
    """
    if env.dinghy:
        local('cd %s && %s' % (env.project_directory, command))
    else:
        docker_compose_run(command)


def docker_compose(command_name):

    domains = '`' + '`, `'.join([env.root_domain] + env.extra_domains) + '`'

    localEnv = {
        'PROJECT_NAME': env.project_name,
        'PROJECT_DIRECTORY': env.project_directory,
        'PROJECT_ROOT_DOMAIN': env.root_domain,
        'PROJECT_DOMAINS': domains,
    }

    with shell_env(**localEnv):
        local('docker-compose -p %s %s %s' % (
            env.project_name,
            ' '.join('-f ' + env.root_dir + '/infrastructure/docker/' + file for file in env.compose_files),
            command_name
        ))


def docker_compose_run(command_name, service="builder", user="app", no_deps=False, workdir=None, port_mapping=False):
    args = [
        'run ',
        '--rm ',
        '-u %s ' % _shell_escape(user),
    ]

    if no_deps:
        args.append('--no-deps ')

    if port_mapping:
        args.append('--service-ports ')

    if workdir is not None:
        args.append('-w %s ' % _shell_escape(workdir))

    docker_compose('%s %s /bin/bash -c "exec %s"' % (
        ' '.join(args),
        _shell_escape(service),
        _shell_escape(command_name)
    ))


def set_local_configuration():
    env.compose_files = ['docker-compose.yml']
    env.dinghy = False
    env.power_shell = False
    env.user_id = 1000

    with quiet():
        try:
            docker_kernel = "%s" % local('docker version --format "{{.Server.KernelVersion}}"', capture=True)
        except:
            docker_kernel = ''

    if platform == "darwin" and docker_kernel.find('linuxkit') != -1:
        env.dinghy = True
    elif platform in ["win32", "win64"]:
        env.power_shell = True
        # Python can't set the vars correctly on PowerShell and local() always calls cmd.exe
        shellProjectName = local('echo %PROJECT_NAME%', capture=True)
        if (shellProjectName != env.project_name):
            domains = '`' + '`, `'.join([env.root_domain] + env.extra_domains) + '`'
            print 'You must manually set environment variables on Windows:'
            print '$Env:PROJECT_NAME="%s"' % env.project_name
            print '$Env:PROJECT_DIRECTORY="%s"' % env.project_directory
            print '$Env:PROJECT_HOSTNAMES="%s"' % env.project_hostnames
            print '$Env:PROJECT_DOMAINS="%s"' % domains
            raise SystemError('Env vars not set (Windows detected)')

    if not env.power_shell:
        env.user_id = int(local('id -u', capture=True))

    if env.user_id > 256000:
        env.user_id = 1000

    env.root_dir = os.path.dirname(os.path.abspath(__file__))


set_local_configuration()
