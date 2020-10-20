How to upgrade from 2.x to 3.0
==============================

This guide will cover the migration from Fabric to Invoke.
At anytime, you can refer the final form of the starter kit by looking
at the [main repository](https://github.com/jolicode/docker-starter)

**WARNING**: Migrating from 2.x to 3.0 can be a fastidious task since a lot of
parts have changed. Moreover, this project is a starter kit. It means you
usually start with it, then you make your own choices and implementations. So if
you are comfortable with your current project **we recommend you to stick on
2.x**. If you want to migrate from 2.x to 3.0, here are the tasks you will have
to perform.

## Fabric to Invoke

1. Rename the `fabfile.py` to `tasks.py`:

    ```bash
    mv fabfile.py tasks.py
    ```

1. Extract the configuration to a dedicated file `invoke.py`:

    1. The variables are not longer prefixed with `env.`:

        * `env.project_name` => `project_name`;
        * `env.root_domain` => `root_domain`;
        * `env.project_directory` => `project_directory`;
        * `env.extra_domains` => `extra_domains`.

    1. Append the following content to the file:

        ```py
        # Usually, you should not edit the file above this point
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


        def __extract_runtime_configuration(config):
            from invoke import run
            from sys import platform
            import os
            import sys
            from colorama import init, Fore
            init(autoreset=True)

            config['root_dir'] = os.path.dirname(os.path.abspath(__file__))

            try:
                docker_kernel = run('docker version --format "{{.Server.KernelVersion}}"', hide=True).stdout
            except:
                docker_kernel = ''

            if platform == "darwin" and docker_kernel.find('linuxkit') != -1:
                config['dinghy'] = True
            elif platform in ["win32", "win64"]:
                config['power_shell'] = True
                # # Python can't set the vars correctly on PowerShell and local() always calls cmd.exe
                shellProjectName = run('echo %PROJECT_NAME%', hide=True).stdout
                if (shellProjectName != config['project_name']):
                    domains = '`' + '`, `'.join([config['root_domain']] + config['extra_domains']) + '`'
                    print(Fore.RED + 'Env vars not set (Windows detected)')
                    print(Fore.YELLOW + 'You must manually set environment variables on Windows:')
                    print('$Env:PROJECT_NAME="%s"' % config['project_name'])
                    print('$Env:PROJECT_DIRECTORY="%s"' % config['project_directory'])
                    print('$Env:PROJECT_ROOT_DOMAIN="%s"' % config['root_domain'])
                    print('$Env:PROJECT_DOMAINS="%s"' % domains)
                    sys.exit(0)

            if not config['power_shell']:
                config['user_id'] = int(run('id -u', hide=True).stdout)

            if config['user_id'] > 256000:
                config['user_id'] = 1000

            return config


        locals().update(__extract_runtime_configuration(locals()))
        ```

    1. You can remove the similar part from `tasks.py` because `invoke.py` will be automatically imported.

        Don't forget to remove `set_local_configuration()` function and its function call.

1. Edit `tasks.py`:

    1. Fix `import`s:

        Remove all imports (unless you are using specific import) and add the following lines:

        ```py
        from invoke import task
        from shlex import quote
        from colorama import Fore
        ```

    1. Replace `run_in_docker_or_locally_for_dinghy()`,  `docker_compose()` and `docker_composer_run()` functions:

        With the following content:

        ```py
        def run_in_docker_or_locally_for_dinghy(c, command, no_deps=False):
            """
            Mac users have a lot of problems running Yarn / Webpack on the Docker stack so this func allow them to run these tools on their host
            """
            if c.dinghy:
                with c.cd(c.project_directory):
                    c.run(command)
            else:
                docker_compose_run(c, command, no_deps=no_deps)


        def docker_compose_run(c, command_name, service="builder", user="app", no_deps=False, workdir=None, port_mapping=False):
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
            ))


        def docker_compose(c, command_name):
            domains = '`' + '`, `'.join([c.root_domain] + c.extra_domains) + '`'

            env = {
                'PROJECT_NAME': c.project_name,
                'PROJECT_DIRECTORY': c.project_directory,
                'PROJECT_ROOT_DOMAIN': c.root_domain,
                'PROJECT_DOMAINS': domains,
            }

            cmd = 'docker-compose -p %s %s %s' % (
                c.project_name,
                ' '.join('-f \'' + c.root_dir + '/infrastructure/docker/' + file + '\'' for file in c.docker_compose_files),
                command_name
            )

            c.run(cmd, pty=True, env=env)
        ```

    1. Use the context in all tasks

        Invoke needs a context (named `c`) in every single task. This context:

        * is configured for each task;
        * contains the configuration;
        * contains some methods to execute commands.

        You will need to update all tasks signature, and all tasks function call to pass the context:

        Before:

        ```py
        @task
        def up():
            build()
            docker_compose('up --remove-orphans -d')
        ```

        After:

        ```py
        @task
        def up(c):
            build(c)
            docker_compose(c, 'up --remove-orphans -d')
        ```

    1. Do not use `env.<var>` anymore:

        Before:

        ```py
        @task
        def up(c):
            print(env.project_name)
        ```

        After:

        ```py
        @task
        def up(c):
            print(c.project_name)
        ```

    1. Do not use `local()` anymore:

        Before:

        ```py
        @task
        def start(c):
            machine_running = local('dinghy status', capture=True)
        ```

        After:

        ```py
        @task
        def start(c):
            machine_running = c.local('dinghy status').stdout
        ```

    1. Add `Builder` class and use it:

        The Builder class is the new `@with_builder` decorator

        ```py
        class Builder:
            def __init__(self, c):
                self.c = c

            def __enter__(self):
                self.docker_compose_files = self.c.docker_compose_files
                self.c.docker_compose_files = ['docker-compose.builder.yml'] + self.docker_compose_files

            def __exit__(self, type, value, traceback):
                self.c.docker_compose_files = self.docker_compose_files
        ```

        Before:

        ```py
        @task
        @with_builder
        def install():
            docker_compose_run('composer install -n --prefer-dist --optimize-autoloader')
        ```

        After:

        ```py
        @task
        def install(c):
            with Builder(c):
                docker_compose_run(c, 'composer install -n --prefer-dist --optimize-autoloader')
        ```

    1. Update colors

        Fabric provided a nice helper for colors. Now we use colorama.

        Before:

        ```py
        print green('You can now browse:')
        for domain in [c.root_domain] + c.extra_domains:
            print yellow("* https://" + domain)
        ```

        After:

        ```py
        @task
        print(Fore.GREEN + 'You can now browse:')
        for domain in [c.root_domain] + c.extra_domains:
            print(Fore.YELLOW + "* https://" + domain)
        ```

## Alpine to Debian

We have changed PHP base images from Alpine to Debian. The best guide to process
this migration is to replicate what have been done in following pull request:
https://github.com/jolicode/docker-starter/pull/67
