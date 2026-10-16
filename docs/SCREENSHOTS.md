# Screenshot Notes

These are real browser captures of [the standalone demo](demo/README.md), using Filament 5.10.2, Livewire 4.4.7, the default Filament theme, and the package's compiled stylesheet. The interface is in English. Task titles, team members, and records are fictional.

No application-specific `viteTheme()` is registered. The demo publishes Filament's vendor assets into its own `public` directory and uses a separate SQLite database.

Images were captured at the browser's default 1280 × 720 viewport. Layout comparisons and the footer-actions example are cropped directly from browser screenshots; styles and data were not painted over or reconstructed.

The four layout comparisons show the same launch tasks across all three columns, with compact actions and the default light theme. Each uses the same 1008 × 544 crop, preserving the differences in card density and spacing.

| Image | State |
| --- | --- |
| [board.jpg](images/board.jpg) | Launch project, Accent cards, compact actions, default light theme. Cropped after the first card row. |
| [board-dark.jpg](images/board-dark.jpg) | The same board using Filament's native dark theme. |
| [filters.jpg](images/filters.jpg) | Priority set to High; native active-filter indicator and filtered counts. |
| [card-default.jpg](images/card-default.jpg) | The launch board with multiple Default cards across three columns. |
| [card-compact.jpg](images/card-compact.jpg) | The same board with Compact cards and tighter spacing. |
| [card-flat.jpg](images/card-flat.jpg) | The same board with Flat rows and subtle dividers between adjacent cards. |
| [card-accent.jpg](images/card-accent.jpg) | The same board with Accent cards and priority-based colored stripes. |
| [compact-actions.jpg](images/compact-actions.jpg) | The onboarding task's header menu open, showing Edit, Mark as done, and Delete. |
| [footer-actions.jpg](images/footer-actions.jpg) | Compact actions disabled; individual native actions in card footers. |
| [create-task.jpg](images/create-task.jpg) | Shared create form filled with a new fictional welcome-checklist task. |
| [edit-task.jpg](images/edit-task.jpg) | Shared edit form hydrated from the onboarding task, with only High selected. |
| [empty-state.jpg](images/empty-state.jpg) | A search with no matching records; each column displays customized empty-state content and an Add task action. |
| [drag-handle.jpg](images/drag-handle.jpg) | Accent card with a dedicated drag handle enabled. |
| [drag-and-drop.jpg](images/drag-and-drop.jpg) | The onboarding task after being dragged from Backlog to In progress and saved. |
| [pagination.jpg](images/pagination.jpg) | Separate 3,000-task dataset; Backlog on its second page while the other columns remain on their first page. |

To reproduce them, start the demo, use **Customize view** to change the presentation, and use the native filters, actions, pagination buttons, and user-menu theme control. The create-form capture adds a task to the local launch project when submitted; reset the demo database to restore the original 11 records.
