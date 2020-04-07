<?php

Route::resource('gii', 'GiiController')->only(['create', 'store']);
