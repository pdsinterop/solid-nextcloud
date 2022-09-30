<?php

namespace Pdsinterop\Solid\Auth;

interface ReplayDetectorInterface
{
    public function detect(string $jti, string $targetUri): bool;
}
