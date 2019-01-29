from fabric.api import task, env
from fabric.operations import local, _shell_escape, settings
from fabric.context_managers import quiet
import os
import re
from sys import platform


@task
def start():
    """
    Be sure that everything is started and installed
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


@task
def up():
    """
    Ensure infrastructure is sync and running
    """
    docker_compose('build')
    docker_compose('up --remove-orphans -d')


@task
def stop():
    """
    Stop the infrastructure
    """
    docker_compose('stop')


@task
def logs():
    """
    Show logs of infrastructure
    """
    docker_compose('logs -f --tail=150')


@task
def install():
    """
    Install frontend application (composer, yarn, assets)
    """
    #docker_compose_run('composer install -n --prefer-dist', 'builder', 'myapp')
    #docker_compose_run('yarn', 'builder', 'myapp')


@task
def cache_clear():
    """
    Clear cache of the frontend application
    """
    #docker_compose_run('rm -rf var/cache/', 'builder', 'myapp', no_deps=True)


@task
def migrate():
    """
    Migrate database schema
    """
    #docker_compose_run('php bin/console doctrine:database:create --if-not-exists', 'builder', 'myapp', no_deps=True)
    #docker_compose_run('php bin/console doctrine:migration:migrate -n', 'builder', 'myapp', no_deps=True)


@task
def ssh():
    """
    Ssh into frontend container
    """
    docker_compose('exec --user=myapp --index=1 frontend /bin/bash')


@task
def builder():
    """
    Bash into a builder container
    """
    docker_compose_run('bash', 'builder', 'myapp')


def docker_compose(command_name):
    local('PROJECT_UID=%s docker-compose -p myapp %s %s' % (
        env.uid,
        ' '.join('-f infrastructure/docker/' + file for file in env.compose_files),
        command_name
    ))


def docker_compose_run(command_name, service, user="myapp", no_deps=False):
    args = [
        'run '
        '--rm '
        '-u %s ' % _shell_escape(user)
    ]

    if no_deps:
        args.append('--no-deps ')

    docker_compose('%s %s /bin/bash -c "%s"' % (
        ' '.join(args),
        _shell_escape(service),
        _shell_escape(command_name)
    ))


def set_local_configuration():
    env.compose_files = ['docker-compose.yml']
    env.uid = int(local('id -u', capture=True))
    env.root_dir = os.path.dirname(os.path.abspath(__file__))

    if env.uid > 256000:
        env.uid = 1000

    with quiet():
        try:
            docker_kernel = "%s" % local('docker version --format "{{.Server.KernelVersion}}"', capture=True)
        except:
            docker_kernel = ''

    if platform == "linux" or platform == "linux2" or docker_kernel.endswith('linuxkit-aufs'):
        env.dinghy = False
    elif platform == "darwin":
        env.dinghy = True


set_local_configuration()
