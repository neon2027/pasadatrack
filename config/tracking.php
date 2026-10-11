<?php

declare(strict_types=1);

return [
    'demo' => (bool) env('TRACKING_DEMO', in_array(config('app.env'), ['local', 'testing'], true)),
];
