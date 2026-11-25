<style>
    /* SmartOra AI - Clean, Spacious RTL Theme (Light & Dark) */
    html,
    body,
    .fi-layout,
    .fi-main {
        overflow-x: clip !important;
    }

    /* Compact Header & Comfortable Main Spacing */
    .fi-main {
        padding-top: 1.25rem !important;
        padding-bottom: 1.5rem !important;
    }

    .fi-header {
        margin-bottom: 0.75rem !important;
        gap: 0.75rem !important;
    }

    .fi-header-heading {
        font-size: 1.35rem !important;
        line-height: 1.35 !important;
        letter-spacing: -0.01em;
    }

    /* Refined Cards, Sections & Tables */
    .fi-section,
    .fi-ta-ctn,
    .fi-wi-stats-overview-stat,
    .fi-fo-tabs {
        border-radius: 1rem !important;
        transition: box-shadow 180ms ease, border-color 180ms ease, transform 180ms ease;
    }

    .fi-wi-stats-overview-stat {
        padding: 1rem 1.15rem !important;
        border: 1px solid rgba(148, 163, 184, 0.22);
    }

    .fi-wi-stats-overview-stat:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 20px -8px rgba(15, 23, 42, 0.12);
    }

    .dark .fi-wi-stats-overview-stat:hover {
        box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.45);
    }

    /* Table Ergonomics — Fit Comfortably Without Horizontal Scroll */
    .fi-ta-table {
        width: 100%;
    }

    .fi-ta-header-cell {
        padding: 0.65rem 0.85rem !important;
        font-size: 0.78rem !important;
        white-space: nowrap;
    }

    .fi-ta-cell {
        padding: 0.65rem 0.85rem !important;
        vertical-align: middle !important;
    }

    .fi-ta-row {
        transition: background-color 150ms ease;
    }

    .fi-ta-row:hover {
        background-color: rgba(148, 163, 184, 0.06);
    }

    .fi-badge {
        white-space: nowrap !important;
    }

    /* Polished Sidebar Navigation */
    .fi-sidebar {
        border-inline-end: 1px solid rgba(148, 163, 184, 0.18) !important;
    }

    .fi-sidebar-item-button {
        border-radius: 0.75rem !important;
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
        transition: background-color 150ms ease, color 150ms ease;
    }

    .fi-sidebar-item-active > .fi-sidebar-item-button {
        font-weight: 700 !important;
    }

    /* Sleek Thin Scrollbars for Internal Panels (Chat / Tables) */
    .smartora-scroll::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    .smartora-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .smartora-scroll::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.35);
        border-radius: 9999px;
    }

    .smartora-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 163, 184, 0.55);
    }
</style>
