<?php

// The Explanation Data contracts under src/Explanation, their generated TypeScript twin and the ComputedValue stubs; synthetic fixture, no database or calculator.
use Tey\Kit\Explanation\ExplanationDocument;
use Tey\Kit\Explanation\ExplanationKind;
use Tey\Kit\Explanation\ExplanationNode;
use Tey\Kit\Explanation\ExplanationValidator;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const FIXTURE = __DIR__.'/fixtures/explanation.json';
const KIT_UI = __DIR__.'/../resources/features/core/components/Kit';

function explanationCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
function explanationRefuses(Closure $operation, string $contains, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException $error) {
        explanationCheck(str_contains($error->getMessage(), $contains), $message.' ('.$error->getMessage().')');

        return;
    }
    throw new RuntimeException('Accepted: '.$message);
}
function fixture(): array
{
    return json_decode(file_get_contents(FIXTURE), true, 512, JSON_THROW_ON_ERROR);
}

// Laravel Data needs only its own provider and default config to hydrate and serialize.
$app = new Illuminate\Foundation\Application(dirname(__DIR__));
$app->instance('config', new Illuminate\Config\Repository(['data' => require dirname($argv[1]).'/spatie/laravel-data/config/data.php']));
$app->instance('env', 'testing');
$app->register(Spatie\LaravelData\LaravelDataServiceProvider::class);
$app->boot();

// Recursive data serializes every field.
$document = ExplanationDocument::from(fixture());
explanationCheck($document->roots[0] instanceof ExplanationNode, 'roots hydrate to ExplanationNode');
explanationCheck($document->toArray() === fixture(), 'the fixture round-trips field for field');

// The generator emits the committed stub.
$output = sys_get_temp_dir().'/kit-explanation-'.bin2hex(random_bytes(5)).'.ts';
try {
    exec('KIT_AUTOLOAD='.escapeshellarg($argv[1]).' '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/bin/generate-explanation-ts.php').' '.escapeshellarg($output).' 2>&1', $lines, $exit);
    explanationCheck($exit === 0, 'the generator runs ('.implode("\n", $lines).')');
    explanationCheck(file_get_contents($output) === file_get_contents(KIT_UI.'/Explanation/explanation.generated.ts.stub'), 'the generated TypeScript stub is current');
} finally {
    @unlink($output);
}

// The neutral fixture reconciles its posted amounts without an evaluator.
$root = fixture()['roots'][0];
explanationCheck($root['value']['minor'] === '20037' && 20037 === 15000 + 5250 - 1013 + 800, 'the total reconciles');
explanationCheck(array_column(array_column($root['children'], 'value'), 'minor') === ['20250', '-1013', '800'], 'children keep posted minors');
explanationCheck($root['children'][1]['rounding']['before'] === '-10.125', 'rounding keeps the unrounded credit');
explanationCheck(fixture()['roots'][1]['value']['minor'] === '10125' && 10125 === (15000 + 5250) / 2, 'the mean reconciles');

// Every kind and a non-terminating mean serialize.
foreach (ExplanationKind::cases() as $kind) {
    $fixture = fixture();
    $node = &$fixture['roots'][1];
    $node['kind'] = $kind->value;
    $node['operation']['bounds'] = ['lower' => '0', 'upper' => '200', 'selected' => 'within'];
    $node['rounding'] = ['policy' => 'Named settlement', 'mode' => 'half-even', 'place' => 2, 'before' => '101.25', 'after' => '101.25'];
    $node['rule'] = $fixture['roots'][0]['children'][1]['rule'];
    $node['unrounded'] = null;
    $node['operation']['divisor'] = '3';
    $node['operation']['exactFraction'] = ['numerator' => '1', 'denominator' => '3'];
    $node['note'] = 'Exact fraction retained; decimal does not terminate.';
    unset($node);
    explanationCheck(ExplanationDocument::from($fixture)->toArray() === $fixture, 'kind '.$kind->value.' serializes');
}

// Allocation and credit metadata are retained exactly.
foreach (['1', '-1'] as $sign) {
    $fixture = fixture();
    $positive = $sign === '1';
    $allocation = ['group' => 'ten-cents', 'policy' => 'Largest remainder', 'exactShareMinor' => $positive ? '3.333' : '-3.333', 'baseMinor' => $positive ? '3' : '-3', 'remainderMinor' => $sign, 'allocatedMinor' => $positive ? '4' : '-4', 'adjustmentMinor' => $sign, 'tieBreak' => 'Stable source order'];
    $fixture['roots'][0]['allocation'] = $allocation;
    explanationCheck(ExplanationDocument::from($fixture)->toArray()['roots'][0]['allocation'] === $allocation, 'allocation sign '.$sign.' is retained');
}

// Malformed versions, sources, operands and decimals refuse.
$mutations = [
    'unsupported schema' => fn (&$f) => $f['schemaVersion'] = '2',
    'float minor' => fn (&$f) => $f['roots'][0]['value']['minor'] = 12.34,
    'duplicate id' => fn (&$f) => $f['roots'][1]['id'] = 'total',
    'negative zero' => fn (&$f) => $f['roots'][0]['value']['minor'] = '-0',
    'exponent minor' => fn (&$f) => $f['roots'][0]['value']['minor'] = '1e2',
    'manual without reason' => fn (&$f) => $f['roots'][0]['children'][2]['inputs'][0]['source']['reason'] = '',
    'unsafe link' => fn (&$f) => $f['roots'][0]['children'][0]['children'][0]['inputs'][0]['source']['link']['url'] = 'javascript:alert(1)',
    'dangling operand' => fn (&$f) => $f['roots'][0]['operation']['operandOrder'][] = 'missing',
    'mean without inclusions' => fn (&$f) => $f['roots'][1]['operation']['includedKeys'] = [],
    'zero divisor' => fn (&$f) => $f['roots'][1]['operation']['divisor'] = '0',
    'NaN rate' => fn (&$f) => $f['roots'][0]['children'][1]['rule']['rate']['exact'] = 'NaN',
    'currency and unit' => fn (&$f) => $f['roots'][0]['value']['unit'] = 'kg',
];
foreach ($mutations as $label => $mutation) {
    $fixture = fixture();
    $mutation($fixture);
    explanationRefuses(fn () => ExplanationDocument::from($fixture), '', $label.' refuses');
}

// Depth, node count, cycles and size budgets fail closed.
$leaf = fixture()['roots'][0]['children'][0]['children'][1];
$node = $leaf;
for ($index = 0; $index < 17; $index++) {
    $parent = $leaf;
    $parent['id'] = 'depth-'.$index;
    $parent['children'] = [$node];
    $node = $parent;
}
$fixture = fixture();
$fixture['roots'] = [$node];
explanationRefuses(fn () => ExplanationDocument::from($fixture), 'budget', 'depth over 16 refuses');
$fixture['roots'] = [];
for ($index = 0; $index < 1001; $index++) {
    $fixture['roots'][] = [...$leaf, 'id' => 'node-'.$index];
}
explanationRefuses(fn () => ExplanationDocument::from($fixture), 'budget', 'over 1000 nodes refuses');
$document = ExplanationDocument::from(fixture());
$document->roots[0]->children[] = $document->roots[0];
explanationRefuses(fn () => ExplanationValidator::validate($document), '', 'a cycle refuses');
$fixture = fixture();
$fixture['roots'][0]['note'] = str_repeat('x', 262145);
explanationRefuses(fn () => ExplanationDocument::from($fixture), '256 KiB', 'over 256 KiB refuses');

// The UI stubs: exports, the internal Popover, and no import outside the kit's own set and its peers.
$index = file_get_contents(KIT_UI.'/index.ts.stub');
foreach (['ComputedValue', 'ComputedValueRoot', 'ComputedValueScope', 'ComputedValueToggle'] as $name) {
    explanationCheck(str_contains($index, "export { default as {$name} } from './Explanation/{$name}.vue';"), $name.' is exported from @kit/ui');
}
explanationCheck(! preg_match('/^export[^\n]*Popover/m', $index), 'Popover is not exported from @kit/ui');
$feature = json_decode(file_get_contents(KIT_UI.'/../../feature.json'), true, 512, JSON_THROW_ON_ERROR)['files'];
foreach ([...glob(KIT_UI.'/Explanation/*.stub'), KIT_UI.'/Overlay/Popover.vue.stub'] as $stub) {
    $relative = substr($stub, strlen(KIT_UI) + 1);
    explanationCheck(($feature['resources/js/components/Kit/'.substr($relative, 0, -5)]['stub'] ?? null) === 'components/Kit/'.$relative, $relative.' is generated by core');
    preg_match_all("/from '([^']+)'/", file_get_contents($stub), $imports);
    foreach ($imports[1] as $import) {
        explanationCheck(in_array($import, ['vue', 'reka-ui', '@lucide/vue', '@inertiajs/vue3'], true) || str_starts_with($import, './') || str_starts_with($import, '@/components/Kit/'), $relative.' imports only kit peers ('.$import.')');
    }
}

// The phone sheet and the overlays under it keep focus and labels (source checks; the browser behaviour is proven in a host, not here).
$popover = file_get_contents(KIT_UI.'/Overlay/Popover.vue.stub');
explanationCheck(str_contains($popover, "closest('[role=\"dialog\"]')?.setAttribute('aria-labelledby', titleId)"), 'the phone sheet labels its dialog with the modal title');
explanationCheck(str_contains($popover, "attributeFilter: ['data-inertiaui-modal-entered']"), 'the phone sheet moves focus only once the modal has entered');
$panel = file_get_contents(KIT_UI.'/Overlay/ModalPanel.vue.stub');
explanationCheck(str_contains($panel, 'data-modal-close') && str_contains($panel, 'max-sm:size-11'), 'the modal Close button is a 44px target on phones');
explanationCheck(str_contains($panel, '@closed="confirmationClosed"') && ! preg_match('/function discard\(\) \{[^}]*emit\(\'close\'\)/', $panel), 'Discard & close closes the modal only after the confirmation has closed');
explanationCheck(str_contains(file_get_contents(KIT_UI.'/Overlay/Dialog.vue.stub'), "@close-auto-focus=\"nextTick(() => emit('closed'))\""), 'the Dialog emits closed once it has returned focus');
$modal = file_get_contents(KIT_UI.'/Overlay/Modal.vue.stub');
explanationCheck(str_contains($modal, 'remember();') && str_contains($modal, 'returnFocus(); emit(\'closed\')'), 'a local Modal returns focus to its opener when it closes');
