<script setup lang="ts">
import { computed } from 'vue';
import { renderMarkdown, type MarkdownOptions } from './markdown';
import { PROSE, TABLE, TEXT_SIZES, type TextSize } from './text';

/*
 * User-authored Markdown, rendered safely (docs/markdown.md): renderMarkdown's GFM, with raw HTML escaped and the output sanitized against an allowlist,
 * styled as TextProse styles its slot. `headingLevel` is the level a `#` heading renders at (default 3, so user content never emits an h1 or h2);
 * `newTab` opens links in a new tab. Every link carries rel="noopener noreferrer nofollow". `size` is sm (the default), base or lg.
 */
const props = withDefaults(
    defineProps<{
        source?: string | null;
        headingLevel?: MarkdownOptions['headingLevel'];
        newTab?: boolean;
        size?: TextSize;
    }>(),
    {
        source: null,
        headingLevel: 3,
        newTab: false,
        size: 'sm',
    },
);

// What Markdown adds to TextProse's elements: TextTable's table with GFM's column alignment, task-list items without a bullet, images kept to the
// column, h5 and h6, struck text muted, and footnotes under a rule.
const MARKDOWN =
    '[&_[align=center]]:text-center [&_[align=right]]:text-end [&_li:has(>input)]:list-none [&_li>input]:me-1.5 [&_li>input]:align-middle [&_ul:has(>li>input)]:ps-1 [&_img]:max-w-full [&_h5]:font-semibold [&_h6]:font-semibold [&_del]:text-muted-foreground [&_sup]:text-[0.75em] [&_.footnotes]:mt-6 [&_.footnotes]:border-t [&_.footnotes]:border-border [&_.footnotes]:pt-3 [&_.footnotes]:text-muted-foreground';

const html = computed(() =>
    props.source
        ? renderMarkdown(props.source, {
              headingLevel: props.headingLevel,
              newTab: props.newTab,
          })
        : '',
);
</script>
<template>
    <div
        :class="[
            'max-w-none text-pretty break-words',
            TEXT_SIZES[size],
            PROSE,
            TABLE,
            MARKDOWN,
        ]"
        data-slot="text-markdown"
        v-html="html"
    />
</template>
