<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;

class SqlName extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $version = db_get_field("SELECT VERSION()");
        $name = 'MySQL';

        if (!empty($version) && stripos($version, 'mariadb') !== false) {
            $name = 'MariaDB';
        }

        return array('name' => $name);
    }
}
