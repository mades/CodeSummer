<?php

namespace CodeHuiter\Pattern\Service;

use CodeHuiter\Config\CompressorConfig;

interface Compressor
{
    /**
     * @return CompressorConfig Updated Compressor Config
     */
    public function checkCompress(): CompressorConfig;
}
