<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('public-updates', fn () => true);
