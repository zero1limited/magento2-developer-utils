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

### Make CLI Command

```text
Description:
  Add a CLI command to an existing extension.

Usage:
  make:cli-command [options] [--] [<--force>]

Arguments:
  --force                                        If supplied and class/file exists,  it will be overwritten [default: false]

Options:
      --name=NAME                                Name of the module (Magento module name, MyCompany_MyModule)
      --command-class-name[=COMMAND-CLASS-NAME]  Name of the class for the command (MyCommand)
      --command-signature[=COMMAND-SIGNATURE]    The signature of the command. (defaults to example:command)
      --command-help[=COMMAND-HELP]              The help/description of the command (default "")
  -h, --help                                     Display this help message
  -q, --quiet                                    Do not output any message
  -V, --version                                  Display this application version
      --ansi                                     Force ANSI output
      --no-ansi                                  Disable ANSI output
  -n, --no-interaction                           Do not ask any interactive question
  -v|vv|vvv, --verbose                           Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```
Example
```bash
./vendor/bin/magentodev make:cli-command
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