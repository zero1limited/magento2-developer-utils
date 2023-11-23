# Magento 2 Developer Cli

This project takes inspiration from the Laravel `artisan make` commands.
This is very much a work in progress, any feedback or contributes are welcome.

## Features

### Vendor Patches
The ability to generate patches from the vendor directory.
For example if you have had to craft a fix for a module and the vendor hasn't supplied a patch yet.

Summary
```bash
./vendor/bin/magentodev vendor:diff
```

Detail / patch generation
```bash
./vendor/bin/magentodev vendor:diff --package=magento/framework
```

### Module Creation
Summary
```bash
./vendor/bin/magentodev module:make
```

## Planned Features

- [ ] Templates for modules
- [ ] Nicer interface for managing submodules
- [ ] Create plugins / components / models automatically.

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