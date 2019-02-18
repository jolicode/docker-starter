# JoliCode's Docker starter kit

## Before using the stack (remove this chapter once done)

### Project configuration

Before executing any command, you need to configure few parameters in the
`fabfile.py` file:

* `env.project_name`: This will be used to prefix all docker objects (network,
 images, containers)
* `env.project_directory`: This is the host directory containing your PHP
  application
* `env.project_hostnames`: This will be all your domain names, separated with comma

### SSL certificate

To save your time with certificate generation, this project already embed a
basic certificate. However, it is auto-signed and does not use the domain name
that you will use. While it will work if you accept this auto-signed
certificate, it's recommended to use more powerful tool like
[mkcert](https://github.com/FiloSottile/mkcert). As mkcert uses a CA root, you
will need to generate a certificate on each host using this stack and so add
`/infrastructure/services/router/certs/` to the `.gitignore` file.

Alternatively, you can configure
`infrastructure/docker/services/router/openssl.cnf` then use
`infrastructure/docker/services/router/generate-ssl.sh` to create your own
certificate. Then you will have to add it to your computer CA store.

*Note*: Some Fabric tasks have been added for DX purposes. Checkout and adapt
the tasks `install`, `migrate` and `cache_clear` to your project

---

## Running the application locally

### Requirements

A Docker environment is provided and requires you to have these tools available:

 * Docker
 * pipenv

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
fab start
```

> Note: the first start of the stack should take a few minutes.

The site is now accessible at the hostnames your have configured over HTTPS
(you may need to accept self-signed SSL certificate).

### Builder

Having some composer, yarn or another modifications to make on the project?
Start the builder which will give you access to a container with all these
tools available:

```bash
fab builder
```

Note: You can add as many fabric command as you want. But the command should be
ran by the builder, don't forget to add `@with_builder` annotation to the
function.

### Other tasks

Checkout `fab -l` to have the list of available fabric tasks.
