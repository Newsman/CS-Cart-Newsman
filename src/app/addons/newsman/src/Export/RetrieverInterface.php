<?php

namespace Tygh\Addons\Newsman\Export;

interface RetrieverInterface
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array());
}
