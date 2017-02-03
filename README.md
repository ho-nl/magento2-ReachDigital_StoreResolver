# H&O Store Resolver

[Changelog](CHANGELOG.md)

You are running a multi store with a specific catalog per domain.

## Installation
```BASH

composer config repositories.honl/magento2-storeresolver vcs git@github.com:ho-nl/magento2-Ho_StoreResolver.git
composer require honl/magento2-storeresolver
```

## Automatic mapping from Domain > Store View

With the new implementation for the `StoreResolverInterface` the domains automatically get mapped to the correct store view. This reduces the need to modify code to get new domain names working.

1. Register your domain
2. Point the A-records to your server 
3. ~~Change index.php, .htaccess or nginx_config file to activate your domain name~~ `StoreResolver` solves this for you.

## Store scope definition

| Product                      | Default | Website | Store Group | Store View |
| ---------------------------- | ------- | ------- | ----------- | ---------- |
| Product prices               | ✔       | ✔       |             |            |
| Product tax class            | ✔       | ✔       |             |            |
| Product status               | ✔       | ✔       |             |            |
| Product visibility           | ✔       | ✔       |             | ✔          |
| Product Inventory            | ✔       |         |             |            |
| Product attributes / transl. | ✔       | ✔       |             | ✔          |  
| Base currency                | ✔       | ✔       |             |            |
| (Default) display currency   | ✔       |         |             | ✔          |
| Category settings            | ✔       |         |             | ✔          |
| System configuration settings| ✔       | ✔       |             | ✔          |
| Root category configuration  |         |         | ✔           |            |
| Orders                       | ✔       |         |             | ✔          |
| Customers                    | ✔       | ✔       |             |            |
