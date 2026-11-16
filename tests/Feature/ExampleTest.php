<?php

test('guests are redirected to the login page', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/items')->assertRedirect('/login');
});

test('the login page is reachable', function () {
    $this->get('/login')->assertOk()->assertSee('Masuk');
});
