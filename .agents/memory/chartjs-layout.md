---
name: Chart.js in layout
description: Chart.js CDN and @stack('scripts') are wired into layouts/app.blade.php.
---

## Rule
- Chart.js 4.4.0 is loaded from CDN in `layouts/app.blade.php` before `@stack('scripts')`.
- To add a chart in any view, use `@push('scripts')...<script>new Chart(...);</script>...@endpush` anywhere in the view (including inside `@section('content')`).
- SQLite date aggregation uses `strftime('%d', fecha)` for day-of-month grouping.

**Why:** The layout previously had no scripts stack, so chart scripts in views would be silently dropped.
