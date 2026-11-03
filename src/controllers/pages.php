<?php
declare(strict_types=1);

function page_home(): void
{
    View::render('home', ['title' => Env::get('APP_NAME') . ' — simple scheduling for your Google Calendar']);
}

function page_pricing(): void
{
    View::render('pricing', ['title' => 'Pricing']);
}

function page_privacy(): void
{
    View::render('legal/privacy', ['title' => 'Privacy Policy']);
}

function page_terms(): void
{
    View::render('legal/terms', ['title' => 'Terms of Service']);
}
