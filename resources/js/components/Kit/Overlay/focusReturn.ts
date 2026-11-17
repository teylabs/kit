// Shared by every Modal: each open (and each close's return) starts a new generation, so a return still watching from an earlier close stops,
// even one from a Modal since unmounted. Reopening from another opener within that watch never sends focus back to the previous opener.
export const focusReturns = { generation: 0 };
