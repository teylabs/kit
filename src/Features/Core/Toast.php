<?php

namespace Tey\Kit\Features\Core;

use Inertia\Inertia;
use InvalidArgumentException;

/**
 * The one way a controller asks for a toast: it flashes Inertia's `toast` ({type, message, description?, action?, from?, style, duration}) and the shell's Toaster
 * renders it. The server decides whether to toast; the page never does. A toast confirms a save or reports a background result. An error that needs fixing is a
 * validation or inline response, so an error toast is never the only signal. An action is one item, never destructive (no delete, remove or the like), and stays up longer.
 * `from('save')` names the trigger by its action-descriptor key (no DOM knowledge here); `style()` picks fly-out, anchored or pulse, else pulse for success, plain otherwise.
 * Use it statically (`Toast::success('Saved')->from('save')`) or through the Toastable concern (`$this->toast('Saved')->from('save')`). Each change re-flashes.
 */
final class Toast
{
    public const TYPES = ['success', 'info', 'warning', 'error'];

    public const STYLES = ['plain', 'fly-out', 'anchored', 'pulse'];

    public const POSITIONS = ['auto', 'top-center', 'top-right', 'bottom-right', 'bottom-center'];

    public const DURATION = 4000;

    public const ACTION_DURATION = 8000;

    /** Milliseconds per type when `kit.toasts.durations` does not say; null stays until dismissed. */
    public const DURATIONS = ['success' => self::DURATION, 'info' => self::DURATION, 'warning' => null, 'error' => null];

    /** Verbs an action label may not start with: a destructive step needs a confirmation the toast cannot give. */
    public const DESTRUCTIVE = ['delete', 'remove', 'destroy', 'discard', 'erase', 'purge', 'revoke', 'cancel', 'archive', 'reset', 'wipe', 'drop', 'terminate', 'deactivate', 'leave'];

    private ?string $from = null;

    private ?string $style = null;

    /** @param array{label: string, href: string, method?: string}|null $action */
    private function __construct(private string $type, private string $message, private ?string $description, private ?array $action)
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('A toast type is one of: '.implode(', ', self::TYPES).'.');
        }
        if (trim($message) === '') {
            throw new InvalidArgumentException('A toast needs a message.');
        }
        $this->action = $action === null ? null : self::action($action);
    }

    /** @param array{label: string, href: string, method?: string}|null $action */
    public static function success(string $message, ?string $description = null, ?array $action = null): self
    {
        return (new self('success', $message, $description, $action))->flash();
    }

    /** @param array{label: string, href: string, method?: string}|null $action */
    public static function info(string $message, ?string $description = null, ?array $action = null): self
    {
        return (new self('info', $message, $description, $action))->flash();
    }

    /** @param array{label: string, href: string, method?: string}|null $action */
    public static function warning(string $message, ?string $description = null, ?array $action = null): self
    {
        return (new self('warning', $message, $description, $action))->flash();
    }

    /** Never the only signal: pair it with the validation or inline state that says what to fix. @param array{label: string, href: string, method?: string}|null $action */
    public static function error(string $message, ?string $description = null, ?array $action = null): self
    {
        return (new self('error', $message, $description, $action))->flash();
    }

    /** The trigger, by its action-descriptor key (the `key` the rendered button carries). The Toaster falls back to a plain toast when it cannot find it. */
    public function from(string $key): self
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException('A toast comes from an action-descriptor key.');
        }
        $this->from = $key;

        return $this->flash();
    }

    /** fly-out (from the trigger into the shell's corner), anchored (a short bubble on the trigger, no shell toast) or pulse (a shell toast and a ring on the trigger). */
    public function style(string $style): self
    {
        if (! in_array($style, self::STYLES, true)) {
            throw new InvalidArgumentException('A toast style is one of: '.implode(', ', self::STYLES).'.');
        }
        $this->style = $style;

        return $this->flash();
    }

    /** The flashed payload. */
    public function toArray(): array
    {
        $toast = ['type' => $this->type, 'message' => $this->message];
        if ($this->description !== null && trim($this->description) !== '') {
            $toast['description'] = $this->description;
        }
        if ($this->action !== null) {
            $toast['action'] = $this->action;
        }
        if ($this->from !== null) {
            $toast['from'] = $this->from;
        }
        $toast['style'] = $this->style ?? ($this->type === 'success' ? 'pulse' : 'plain');
        $toast['duration'] = $this->duration();

        return $toast;
    }

    /** What every page shares as `kitToasts`: where the shell's Toaster sits (`kit.toasts.position`), `auto` for an unknown value. */
    public static function shared(): array
    {
        $position = config('kit.toasts.position', 'auto');

        return ['position' => in_array($position, self::POSITIONS, true) ? $position : 'auto'];
    }

    /**
     * The type's duration from `kit.toasts.durations` at flash time (ms, or null to stay until dismissed). A missing or unusable value is the type's default; an
     * action keeps a timed toast up at least ACTION_DURATION.
     */
    private function duration(): ?int
    {
        $durations = config('kit.toasts.durations');
        $duration = is_array($durations) && array_key_exists($this->type, $durations) ? $durations[$this->type] : self::DURATIONS[$this->type];
        if ($duration !== null && (! is_int($duration) || $duration <= 0)) {
            $duration = self::DURATIONS[$this->type];
        }
        if ($duration === null) {
            return null;
        }

        return $this->action === null ? $duration : max($duration, self::ACTION_DURATION);
    }

    private function flash(): self
    {
        Inertia::flash('toast', $this->toArray());

        return $this;
    }

    /** @param array<mixed> $action */
    private static function action(array $action): array
    {
        $label = $action['label'] ?? null;
        $href = $action['href'] ?? null;
        $method = strtolower((string) ($action['method'] ?? 'get'));
        if (! is_string($label) || trim($label) === '' || ! is_string($href) || $href === '' || array_diff(array_keys($action), ['label', 'href', 'method']) !== []) {
            throw new InvalidArgumentException('A toast action is one item: a label and an href, with an optional method.');
        }
        if (! in_array($method, ['get', 'post', 'put', 'patch'], true)) {
            throw new InvalidArgumentException('A toast action is never destructive: its method is get, post, put or patch.');
        }
        $verb = strtolower((string) strtok(trim($label), " \t"));
        if (in_array($verb, self::DESTRUCTIVE, true)) {
            throw new InvalidArgumentException("A toast action is never destructive: \"{$label}\" needs a confirmation, not a toast.");
        }

        return ['label' => $label, 'href' => $href, 'method' => $method];
    }
}
