# My project

## Running the application locally

### Requirements

A Docker environment is provided and requires you to have these tools available:

 * Docker
 * Bash
 * pipenv (see [these instructions](https://pipenv.readthedocs.io/en/latest/install/) for how to install)

Install and run `pipenv` to install the required tools:

```bash
pipenv --three install
```

You can configure your current shell to be able to use Invoke commands directly
(without having to prefix everything by `pipenv run`)

```bash
pipenv shell
```

Optionally, in order to improve your usage of invoke scripts, you can install console autocompletion script.

If you are using bash:

```bash
invoke --print-completion-script=bash > /etc/bash_completion.d/invoke
```

If you are using something else, please refer to your shell documentation.
You may need to use `invoke --print-completion-script=zsh > /to/somewhere`.

Invoke supports completion for `bash`, `zsh` & `fish` shells.

### Docker environment

The Docker infrastructure provides a web stack with:
 - NGINX
 - PostgreSQL
 - PHP
 - Traefik
 - A container with some tooling:
   - Composer
   - Node
   - Yarn / NPM

### Domain configuration (first time only)

Before running the application for the first time, ensure your domain names
point the IP of your Docker daemon by editing your `/etc/hosts` file.

This IP is probably `127.0.0.1` unless you run Docker in a special VM (docker-machine, dinghy, etc).

Note: The router binds port 80 and 443, that's why it will work with `127.0.0.1`

```
echo '127.0.0.1 <your hostnames>' | sudo tee -a /etc/hosts
```

Using dinghy? Run `dinghy ip` to get the IP of the VM.

### Starting the stack

Launch the stack by running this command:

```bash
inv start
```

> Note: the first start of the stack should take a few minutes.

The site is now accessible at the hostnames your have configured over HTTPS
(you may need to accept self-signed SSL certificate).

### Builder

Having some composer, yarn or other modifications to make on the project?
Start the builder which will give you access to a container with all these
tools available:

```bash
inv builder
```

Note: You can add as many Invoke commands as you want. If a command should be
ran by the builder, don't forget to use `with Builder(c):`:
```
@task
def mycommand(c):
    """
    My documentation
    """
    with Builder(c):
        docker_compose_run(c, 'echo "HelloWorld")
```

### Other tasks

Checkout `inv -l` to have the list of available Invoke tasks.
