<?php

namespace App\Services\Widget;

class WorkspaceWidgetKeyGenerator
{
    public function generate(): string
    {
        return 'sift_w_'.$this->randomToken();
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
