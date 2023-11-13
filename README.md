## Deving on module

1. `git submodule add --name Zero1_MagentoDeveloperUtils --branch dev git@github.com:zero1limited/magento2-developer-utils.git extensions/zero1/magento2-developer-utils`
2. 
  ```json
  "zero1-dev-extensions": {
        "type": "path",
        "url": "extensions/zero1/magento2-developer-utils",
        "options": {
            "symlink": true
        }
    }
  ```
3. `composer require --dev zero1/magento-dev:@dev`