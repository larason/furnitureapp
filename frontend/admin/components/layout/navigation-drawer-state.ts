export type NavigationDrawerAction = { readonly type: "open" } | { readonly type: "dismiss" } | { readonly type: "navigate" } | { readonly type: "desktop" };

/**
 * Mobile navigation drawer state. Selecting a destination closes the drawer, and
 * the drawer closes when the layout switches to the persistent desktop sidebar.
 */
export function navigationDrawerReducer(state: boolean, action: NavigationDrawerAction): boolean {
  return action.type === "open";
}
