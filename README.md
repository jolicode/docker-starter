# JoliCode's Docker starter kit

## Before using the stack (remove this chapter once done)

After copy/pasting this starter kit to your project and before first launch
you will need to (in this order):

 * find and replace all mentions of myapp.joli with the domain of your choice
 * find and replace all mentions of myapp with the name of the project

Example CLI commands for a project `toto` available on local.toto.com:

```bash
find ./ -type f -exec sed -i -e 's/myapp.joli/local.toto.com/g' {} \;
find ./ -type f -exec sed -i -e 's/myapp/toto/g' {} \;
```

>*Note*: The name of your project will be used as a prefix for docker container
> names, as the user inside the container, as the password of your root database
> user and for some other small things. A perfect name would not contain a dash
> to avoid any side effects.

Generate the SSL certificate to use in the local stack:

```bash
cd infrastructure/development/services/router
./generate-ssl.sh
cd -
```

You are ready to go!

>*Note*: Some Fabric tasks have been added for DX purposes. Checkout and adapt
> the tasks `install`, `migrate` and `cache_clear` to your project
    
## Running the app locally

### Requirements

A docker environment is provided and requires you to have these tools available:

 * docker
 * docker-compose
 * fabric

Install and run `pipenv` to install the required tools:

```bash
pipenv install
```

You can configure your current shell to be able to use fabric commands directly
(without having to prefix everything by `pipenv run`)

```bash
pipenv shell
```

### Domain configuration (first time only)

Before running the app for the first time, ensure the domain name `myapp.joli`
point the IP of your Docker deamon by editing your `/etc/hosts` file.

This IP is probably 127.0.0.1 unless you run Docker in a special VM (docker-machine, dinghy, etc).

```
echo '127.0.0.1 myapp.joli' | sudo tee -a /etc/hosts
```

Using dinghy? Run `dinghy ip` to get the IP of the VM.

### Starting the stack

Launch the stack by running this command:

```bash
fab start
```

> Note: the first start of the stack should take a few minutes.

The site is now accessible at [https://myapp.joli](https://myapp.joli)
(you may need to accept self-signed SSL certificate).

### Running tests (unit & functional)

```bash
fab tests
```

### Builder

Having some composer, yarn or another modifications to make on the project?
Start the builder which will give you access to a container with all these
tools available:

```bash
fab builder
```

### Other tasks

Checkout `fab -l` to have the list of available fabric tasks.
