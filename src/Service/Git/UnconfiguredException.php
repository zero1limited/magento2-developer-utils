<?php

namespace Zero1\MagentoDev\Service\Git;

class UnconfiguredException extends \Exception
{
    public function __construct($username, $email)
    {
        parent::__construct(sprintf(
            'Both user.name and user.email need to be configured. user.name: "%s", user.email: "%s"',
            $username,
            $email
        ));
    }
}