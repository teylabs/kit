<?php

namespace Tey\Kit\Features\Core\ViewModels;

/**
 * Props for a component, not a page: a parent ViewModel returns it from a prop method, the page passes it on with
 * `<Summary v-bind="vm.summary" />`, and the component declares `defineProps<SummaryViewModel>()`. A list of them is
 * `@return list<RowViewModel>`, passed on with `v-for` and `v-bind`. It has no page concerns (no section or title), so the
 * typed-pages rule expects no page for it. It serialises like any ViewModel: the parent's toArray() nests its payload.
 */
abstract class ComponentViewModel extends ViewModel {}
