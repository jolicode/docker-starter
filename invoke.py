# This will be used to prefix all docker objects (network, images, containers)
project_name = 'app'
# This is the root domain where the app will be available
# The "frontend" container will receive all the traffic
root_domain = project_name + '.test'
# This contains extra domains where the app will be available
# The "frontend" container will receive all the traffic
extra_domains = []
# This is the host directory containing your PHP application
project_directory = 'application'

# Usually, you should not edit the file above this point
php_version = '8.0'
docker_compose_files = [
    'docker-compose.yml',
    'docker-compose.worker.yml',
]
services_to_build_first = [
    'php-base',
    'builder',
]
dinghy = False
power_shell = False
user_id = 1000
root_dir = '.'
start_workers = False
composer_cache_dir = '~/.composer/cache'


def __extract_runtime_configuration(config):
    from invoke import run
    from sys import platform
    import os
    import sys
    from colorama import init, Fore
    init(autoreset=True)

    config['root_dir'] = os.path.dirname(os.path.abspath(__file__))

    if os.path.exists(config['root_dir'] + '/infrastructure/docker/docker-compose.override.yml'):
        config['docker_compose_files'] += ['docker-compose.override.yml']

    composer_cache_dir = run('composer global config cache-dir -q', warn=True, hide=True).stdout
    if composer_cache_dir:
        config['composer_cache_dir'] = composer_cache_dir.strip()

    if platform == "darwin":
        try:
            docker_kernel = run('docker version --format "{{.Server.KernelVersion}}"', hide=True).stdout
        except:
            docker_kernel = ''

        if docker_kernel.find('boot2docker') != -1:
            config['dinghy'] = True
        else:
            config['docker_compose_files'] += ['docker-compose.docker-for-x.yml']
    elif platform in ["win32", "win64"]:
        config['docker_compose_files'] += ['docker-compose.docker-for-x.yml']
        config['power_shell'] = True
        # # Python can't set the vars correctly on PowerShell and local() always calls cmd.exe
        shellProjectName = run('echo %PROJECT_NAME%', hide=True).stdout

        if (shellProjectName.rstrip() != config['project_name']):
            domains = '`' + '`, `'.join([config['root_domain']] + config['extra_domains']) + '`'
            print(Fore.RED + 'Env vars not set (Windows detected)')
            print(Fore.YELLOW + 'You must manually set environment variables on Windows:')
            # This list should be in sync with the one in docker_compose.py, docker_compose() function
            print('$Env:PROJECT_NAME="%s"' % config['project_name'])
            print('$Env:PROJECT_DIRECTORY="%s"' % config['project_directory'])
            print('$Env:PROJECT_ROOT_DOMAIN="%s"' % config['root_domain'])
            print("$Env:PROJECT_DOMAINS='%s'" % domains)
            print('$Env:COMPOSER_CACHE_DIR="%s"' % config['composer_cache_dir'])
            print('$Env:PHP_VERSION="%s"' % config['php_version'])
            sys.exit(1)

    if not config['power_shell']:
        config['user_id'] = int(run('id -u', hide=True).stdout)

    if config['user_id'] > 256000:
        config['user_id'] = 1000

    if config['user_id'] == 0:
        print(Fore.YELLOW + 'Running as root? Fallback to fake user id.')
        config['user_id'] = 1000

    return config


locals().update(__extract_runtime_configuration(locals()))
