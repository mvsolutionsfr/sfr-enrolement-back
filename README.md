# enrolement-back

Le projet enrôlement ou inscription permet depuis un portail unique SFR d'inscrire des terminaux sur une des 3 programmes MDM (mobile device managment) Apple, Samsung et Zerotouch.
Le MDM propose une méthode d'enrôlement et de configuration automatique des appareils.

SFR en tant que fournisseur de terminaux, propose a ses clients de faire l'enrôlement de façon automatique mais ne gère pas la configuration.

Ce projet enrolement-back est la partie serveur de l'application Web d'inscription.
Un autre projet (enrolement-front) s'occupe de la partie visuelle et interagit avec le back.

Ce projet s'appuie sur le framework Symfony v8 et sur PHP 8.5


## Installation

Le projet peut être déployé sur Docker et sur K8s


### docker-compose et images

La construction de l'image finale déployée en production s'effectue en 2 étapes
1. Construction de l'image de base contenant les packages nécessaires
2. Déploiement de l'image sur Arbor
3. Construction de l'image finale s'appuyant sur l'image de base et embarquant l'application

#### Construction de l'image de base

Le fichier docker-compose-base.yml contient un service nommé image-php qui permet de construire l'image de base en utilisant le dockerfile spécifique Dockerfile-alpine-php85.
Cette image se nomme enrolement-php85-symfony8.
Pour la construire on utilise la commande:
```bash
docker compose  -f docker-compose-base.yml build image_php
```
Puis
```bash
docker images
```
pour vérifier l'image

Il faut ensuite pousser l'image sur Arbor
```bash
eric@NB-G1218276:/var/www/enrolement-back$ docker login -u erave https://hub.valentine.sfr.com/
Password:

WARNING! Your credentials are stored unencrypted in '/home/eric/.docker/config.json'.
Configure a credential helper to remove this warning. See
https://docs.docker.com/go/credential-store/
```
**Le mot de passe a utilisé est celui que l'on trouve sur Arbor dans le User profile / CLI secret.**

Si l'ID de l'image à déployer est e9c549e31d0f, il faut taguer l'image et l'associé au repository 
```bash
docker tag e9c549e31d0f hub.valentine.sfr.com/enrolement-sbd/enrolement-back:enrolement-php85-symfony8
```
Puis ensuite pousser l'image sur Arbor
```bash
docker push hub.valentine.sfr.com/enrolement-sbd/enrolement-back:enrolement-php85-symfony8
```
