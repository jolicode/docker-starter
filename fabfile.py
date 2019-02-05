from fabric.api import task, env, shell_env
from fabric.operations import local, _shell_escape, settings
from fabric.context_managers import quiet
import os
import re
from sys import platform


# This will be used to prefix all docker objects (network, images, containers)
env.project_name = 'app'
# This is the host directory containing your PHP application
env.project_directory = 'app'
# This will be all your domain name, separated with comma
env.projet_hostnames = 'app.test'

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
    command = 'build'
    command += ' --build-arg PROJECT_NAME=%s' % env.project_name
    command += ' --build-arg USER_ID=%s' % env.user_id

    docker_compose(command)
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
    #docker_compose_run('composer install -n --prefer-dist --optimize-autoloader')
    #docker_compose_run('yarn')


@task
def cache_clear():
    """
    Clear cache of the frontend application
    """
    #docker_compose_run('rm -rf var/cache/', no_deps=True)


@task
def migrate():
    """
    Migrate database schema
    """
    #docker_compose_run('bin/console doctrine:database:create --if-not-exists', no_deps=True)
    #docker_compose_run('bin/console doctrine:migration:migrate -n', no_deps=True)


@task
def builder():
    """
    Bash into a builder container
    """
    docker_compose_run('bash')


@task
def down():
    """
    Clean the infrastructure (remove container, volume, networks)
    """
    docker_compose('down --volumes --rmi=local')


def docker_compose(command_name):
    localEnv = {
        'PROJECT_NAME': env.project_name,
        'PROJECT_DIRECTORY': env.project_directory,
        'PROJET_HOSTNAMES': env.projet_hostnames,
    }

    with shell_env(**localEnv):
        local('docker-compose -p %s %s %s' % (
            env.project_name,
            ' '.join('-f infrastructure/docker/' + file for file in env.compose_files),
            command_name
        ))


def docker_compose_run(command_name, service="builder", user="app", no_deps=False):
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
    env.user_id = int(local('id -u', capture=True))
    env.root_dir = os.path.dirname(os.path.abspath(__file__))

    if env.user_id > 256000:
        env.user_id = 1000

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
