<?php

use Illuminate\Support\Facades\Facade;
use Tey\Kit\Features\Core\EnvironmentIndicator;

require dirname(__DIR__).'/vendor/autoload.php';

/** The environment indicator: off in production, per environment, no app names, 3:1 on both shells. Synthetic config only. */
function indicatorCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

$app = new Illuminate\Foundation\Application(dirname(__DIR__));
Illuminate\Container\Container::setInstance($app);
$app->instance('config', new Illuminate\Config\Repository(['kit' => ['brand' => [], 'environment_indicator' => []]]));
Facade::setFacadeApplication($app);
$app['env'] = 'staging';

$shipped = require dirname(__DIR__).'/config/kit.php';
config(['kit.environment_indicator' => $shipped['environment_indicator']]);
indicatorCheck(array_keys($shipped['environment_indicator']) === ['staging'], 'the shipped config names staging and nothing else');
indicatorCheck(EnvironmentIndicator::current() === ['label' => 'Staging', 'colour' => '#b45309'], 'staging shows the shipped label and colour');
indicatorCheck(EnvironmentIndicator::title('Cases · Product') === '[Staging] Cases · Product', 'the document title carries the label first');
indicatorCheck(EnvironmentIndicator::problems('#b45309') === [], 'the shipped colour is 3:1 or better on the light and the dark shell');

$app['env'] = 'production';
config(['kit.environment_indicator' => ['production' => ['label' => 'Live', 'colour' => '#b45309'], 'staging' => ['label' => 'Staging', 'colour' => '#b45309']]]);
indicatorCheck(EnvironmentIndicator::current() === null && EnvironmentIndicator::title('Cases · Product') === 'Cases · Product', 'production is never marked, even when the config names it');

foreach (['local', 'testing', 'demo'] as $other) {
    $app['env'] = $other;
    indicatorCheck(EnvironmentIndicator::current() === null && EnvironmentIndicator::title('X') === 'X', "{$other} has no entry, so no indicator and an unchanged title");
}

$app['env'] = 'staging';
config(['kit.environment_indicator' => []]);
indicatorCheck(EnvironmentIndicator::current() === null, 'an empty config turns it off everywhere');
foreach (['light on the light shell' => '#fde68a', 'dark on the dark shell' => '#27272a', 'not a hex' => 'red;}body{display:none', 'short hex' => '#b45'] as $why => $colour) {
    config(['kit.environment_indicator' => ['staging' => ['label' => 'Staging', 'colour' => $colour]]]);
    indicatorCheck(EnvironmentIndicator::current() === null && EnvironmentIndicator::problems($colour) !== [], "a colour that fails ({$why}) is off, not approximated");
}
foreach (['', '<b>x</b>', str_repeat('a', 21), "Line\nbreak", '-leading'] as $label) {
    config(['kit.environment_indicator' => ['staging' => ['label' => $label, 'colour' => '#b45309']]]);
    indicatorCheck(EnvironmentIndicator::current() === null, 'an unsafe or unreadable label ('.json_encode($label).') is off');
}
config(['kit.environment_indicator' => ['staging' => ['label' => 'UAT 2', 'colour' => '#b45309']], 'kit.brand.background' => '#78350f']);
indicatorCheck(EnvironmentIndicator::current() === null && str_contains(implode(' ', EnvironmentIndicator::problems('#b45309')), 'the light shell'), "the edge is read against the brand's own page colour");
config(['kit.brand.background' => null]);
indicatorCheck(EnvironmentIndicator::current()['label'] === 'UAT 2', 'a label with a space and a digit is allowed');

// The wiring: the title, the shared prop and the edge in the shell.
$root = dirname(__DIR__);
indicatorCheck(str_contains(file_get_contents($root.'/src/Features/SessionUi/PageTitle.php'), 'EnvironmentIndicator::title('), 'PageTitle, the one source of document titles, applies the prefix');
indicatorCheck(str_contains(file_get_contents($root.'/src/Features/SessionUi/Http/Middleware/HandleInertiaRequests.php'), "'environment' => fn (): ?array => EnvironmentIndicator::current()"), 'the server shares the environment, null where there is none');
$layout = file_get_contents($root.'/resources/features/session-ui/pages/Workspace/Layout.vue.stub');
indicatorCheck(str_contains($layout, 'v-if="page.props.environment"') && str_contains($layout, 'data-environment-edge') && str_contains($layout, 'h-0.5') && str_contains($layout, 'sr-only'), 'the shell draws a 2px edge only when the server sent an environment, named for a screen reader');
indicatorCheck(! preg_match('/environment[^\n]*(badge|pill)/i', $layout), 'the shell has no environment badge');
echo "Environment indicator checks only: no browser render here (see the PR for the headless proof) and no host layout.\n";
