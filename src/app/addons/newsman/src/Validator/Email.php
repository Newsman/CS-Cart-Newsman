<?php

namespace Tygh\Addons\Newsman\Validator;

class Email
{
    /**
     * @param string $email
     * @return bool
     */
    public function isValid($email)
    {
        if (empty($email) || strpos($email, '@') === false) {
            return false;
        }

        $parts = explode('@', $email, 2);
        $local = $parts[0];
        $domain = $parts[1];

        if (function_exists('idn_to_ascii')) {
            $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (false === $asciiDomain) {
                return false;
            }
            $email = $local . '@' . $asciiDomain;
        }

        return false !== filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
