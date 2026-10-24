<?php

namespace Tey\Kit\Mail;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Traits\Macroable;

/**
 * The fluent mail composer (pattern: all mail = Notification + FluentMailMessage, never templates). A notification composes its whole
 * body in PHP as ordered blocks; one generic view renders them through the mail layout in use (the kit's branded one when `mail.brand`
 * is applied, Laravel's otherwise), so no mail ever owns a markdown or Blade template. Text is escaped by default (HTML and markdown);
 * markdown()/markdownLines()/markdownView() are the explicit raw escape hatches. Only markdown components and simple tables are used,
 * so output stays email-client safe. Stock line()/greeting()/action() keep working and land in order. It needs no service provider:
 * the constructor registers its view namespace and component path.
 */
class FluentMailMessage extends MailMessage
{
    use Macroable {
        __call as macroCall;
    }

    public const string VIEW = 'kit-fluent-mail::message';

    /** @var list<array<string, mixed>> */
    public array $blocks = [];

    public ?string $preheaderText = null;

    public function __construct()
    {
        $this->markdown = self::VIEW;
        self::register();
    }

    /** Inbox-preview text after the subject (hidden in the body, omitted from the text part). */
    public function preheader(string $text): static
    {
        $this->preheaderText = $text;

        return $this;
    }

    public function headline(string $text): static
    {
        return $this->markdown('# '.self::escape($text));
    }

    /** @param  string|Htmlable  $greeting */
    public function greeting($greeting): static
    {
        $this->greeting = (string) $greeting;

        return $this->paragraph((string) $greeting);
    }

    /** @param  mixed  $line */
    public function line($line): static
    {
        return $this->paragraph((string) $line);
    }

    public function paragraph(string $text): static
    {
        return $this->markdown(self::escape($text));
    }

    /** @param  array<string, scalar|null>  $facts  label => value, rendered as bold label lines (null values skipped). */
    public function facts(array $facts): static
    {
        $lines = [];
        foreach ($facts as $label => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = '**'.self::escape((string) $label).':** '.self::escape((string) $value);
            }
        }

        return $lines === [] ? $this : $this->markdown(implode("  \n", $lines));
    }

    /** @param  list<string>  $headers  @param  list<list<scalar|null>>  $rows */
    public function table(array $headers, array $rows): static
    {
        $cell = fn (mixed $value): string => self::escape((string) $value);
        $lines = ['| '.implode(' | ', array_map($cell, $headers)).' |', '|'.str_repeat(' --- |', count($headers))];
        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', array_map($cell, $row)).' |';
        }

        return $this->block('table', implode("\n", $lines));
    }

    public function panel(string $text): static
    {
        return $this->block('panel', self::escape($text));
    }

    public function panelIf(bool $condition, string $text): static
    {
        return $condition ? $this->panel($text) : $this;
    }

    /** A highlighted panel with a bold lead-in, e.g. callout('Heads up', 'The link expires tomorrow.'). */
    public function callout(string $title, string $text): static
    {
        return $this->block('panel', '**'.self::escape($title).'**  '."\n".self::escape($text));
    }

    /** A quiet italic aside. */
    public function note(string $text): static
    {
        return $this->markdown('*'.self::escape($text).'*');
    }

    /** @param  iterable<string>  $items */
    public function bullets(iterable $items): static
    {
        $lines = [];
        foreach ($items as $item) {
            $lines[] = '- '.self::escape($item);
        }

        return $lines === [] ? $this : $this->markdown(implode("\n", $lines));
    }

    public function quote(string $text): static
    {
        return $this->markdown(implode("\n", array_map(fn (string $line): string => '> '.self::escape($line), explode("\n", $text))));
    }

    public function divider(): static
    {
        return $this->markdown('---');
    }

    /** @param  'primary'|'success'|'error'|'secondary'  $style  secondary = a plain link line instead of a filled button. */
    public function button(string $text, string $url, string $style = 'primary'): static
    {
        if ($style === 'secondary') {
            return $this->markdown('['.self::escape($text).']('.self::url($url).')');
        }

        return $this->block('button', $text, ['url' => $url, 'color' => $style]);
    }

    /** @param  string  $text  @param  string  $url */
    public function action($text, $url): static
    {
        $this->actionText = $text;
        $this->actionUrl = $url;

        return $this->button($text, $url);
    }

    /** @param  Closure(static): mixed  $compose */
    public function section(string $heading, Closure $compose): static
    {
        $this->markdown('## '.self::escape($heading));
        $compose($this);

        return $this;
    }

    /** Raw markdown, NOT escaped: only for trusted, author-written strings. */
    public function markdown($markdown, array $data = []): static
    {
        if ($markdown === self::VIEW) {
            return $this;
        }

        return $this->block('markdown', $markdown);
    }

    /** @param  iterable<string>  $lines  raw markdown lines, NOT escaped. */
    public function markdownLines(iterable $lines): static
    {
        foreach ($lines as $line) {
            $this->markdown($line);
        }

        return $this;
    }

    /** @param  array<string, mixed>  $data  a reusable markdown partial (rendered in the current mail mode). */
    public function markdownView(string $view, array $data = []): static
    {
        return $this->block('view', $view, ['data' => $data]);
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return [...parent::data(), 'blocks' => $this->blocks, 'preheader' => $this->preheaderText];
    }

    /** @param  array<int, mixed>  $parameters */
    public function __call($method, $parameters)
    {
        return $this->macroCall($method, $parameters);
    }

    /** Makes the generic view and the preheader component resolvable. Idempotent; the host's own mail paths keep priority. */
    public static function register(): void
    {
        $views = dirname(__DIR__, 2).'/resources/views/fluent-mail';
        app('view')->addNamespace('kit-fluent-mail', $views);
        $paths = (array) config('mail.markdown.paths', []);
        if (! in_array($views, $paths, true)) {
            config(['mail.markdown.paths' => [...$paths, $views]]);
        }
    }

    /** HTML-escape and neutralise markdown syntax so user text renders literally. */
    public static function escape(string $text): string
    {
        $text = addcslashes(e($text, false), '\\`*_[]#|~');

        return (string) preg_replace(['/^(\s*)([+\-]|\d+\.)(\s)/m', '/^(\s*)&gt;/m'], ['$1\\\\$2$3', '$1\\&gt;'], $text);
    }

    private static function url(string $url): string
    {
        return str_replace([' ', '(', ')'], ['%20', '%28', '%29'], $url);
    }

    /** @param  array<string, mixed>  $extra */
    private function block(string $type, string $content, array $extra = []): static
    {
        $this->blocks[] = ['type' => $type, 'content' => $content, ...$extra];

        return $this;
    }
}
