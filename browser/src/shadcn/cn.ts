// shadcn-vue's `cn` (its lib/utils.ts): clsx, then tailwind-merge. The vendored components import it as "cn", the registry's placeholder.
import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
