<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;

class SqlVersion extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $version = db_get_field("SELECT VERSION()");
        $cleaned = preg_replace('/[^0-9.].*$/', '', $version);

        return array('version' => $cleaned);
    }
}
