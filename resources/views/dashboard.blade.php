@extends('layouts/default')
{{-- Page title --}}
@section('title')
{{ trans('general.dashboard') }}
@parent
@stop

{{-- Custom CSS for modern dashboard --}}
@push('css')

<style>
/* Modern Dashboard Styles */
.modern-dashboard {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.dashboard-header {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    margin-bottom: 2rem;
    border-left: 4px solid #3498db;
}

.dashboard-header h1 {
    margin: 0;
    color: #2c3e50;
    font-weight: 600;
    font-size: 2rem;
}

.dashboard-header .subtitle {
    color: #7f8c8d;
    margin-top: 0.5rem;
    font-size: 1.1rem;
}

/* Modern Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--card-color);
}

.stat-card.assets { --card-color: #1abc9c; }
.stat-card.licenses { --card-color: #e74c3c; }
.stat-card.accessories { --card-color: #f39c12; }
.stat-card.consumables { --card-color: #9b59b6; }
.stat-card.components { --card-color: #f1c40f; }
.stat-card.users { --card-color: #3498db; }

.stat-card-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-card-info h3 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    color: #2c3e50;
    line-height: 1;
}

.stat-card-info p {
    margin: 0.5rem 0 0 0;
    color: #7f8c8d;
    font-weight: 500;
    font-size: 1rem;
}

.stat-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--card-color);
    color: white;
    font-size: 1.5rem;
}

.stat-card-footer {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #ecf0f1;
    display: flex;
    align-items: center;
    color: var(--card-color);
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
}

.stat-card-footer:hover {
    color: var(--card-color);
    text-decoration: none;
}

.stat-card-footer i {
    margin-left: 0.5rem;
    transition: transform 0.3s ease;
}

.stat-card:hover .stat-card-footer i {
    transform: translateX(4px);
}

/* Modern Content Cards */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
    align-items: start; /* Evita esticamento desnecessário - alinha no topo */
    min-height: 600px; /* Altura mínima consistente para o grid */
}

.modern-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    min-height: 500px; /* Altura mínima reduzida para melhor flexibilidade */
    height: fit-content; /* Altura baseada no conteúdo ao invés de 100% */
    max-height: 800px; /* Altura máxima para evitar containers muito altos */
}

.modern-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.modern-card-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #ecf0f1;
    background: #fafbfc;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0; /* Impede que o header encolha */
}

.modern-card-title {
    margin: 0;
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.25rem;
}

.modern-card-body {
    padding: 2rem;
    flex: 1; /* Ocupa todo o espaço restante */
    display: flex;
    flex-direction: column;
    justify-content: space-between; /* Distribui o conteúdo uniformemente */
}

/* Chart Card Specific */
.chart-card {
    grid-column: span 1;
}

/* Activity Card Specific - garante altura consistente */
.activity-card .modern-card-body {
    min-height: 500px; /* Altura mínima do conteúdo */
}

.activity-card .table-responsive {
    flex: 1; /* A tabela ocupa o espaço disponível */
    margin-bottom: 1.5rem;
}

.activity-card .text-center {
    margin-top: auto; /* Empurra o botão para o final */
    flex-shrink: 0; /* Impede que o botão encolha */
}

/* Dashboard Navigation Icons */
.dashboard-nav-icons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.icon-nav-btn {
    background: transparent;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 0.75rem;
    color: #6c757d;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    outline: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.icon-nav-btn:hover {
    background: #f8f9fa;
    color: #495057;
    transform: translateY(-2px);
    border-color: #adb5bd;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.icon-nav-btn:focus {
    outline: 2px solid #3498db;
    outline-offset: 2px;
}

.icon-nav-btn.active {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    border-color: #2980b9;
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
    transform: translateY(-1px);
}

.icon-nav-btn.active:hover {
    background: linear-gradient(135deg, #2980b9 0%, #1f5f8b 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(52, 152, 219, 0.4);
}

.icon-nav-btn i {
    font-size: 1rem;
    transition: transform 0.2s ease;
}

.icon-nav-btn:hover i {
    transform: scale(1.1);
}

.icon-nav-btn.active i {
    transform: scale(1.05);
}

/* Enhanced tooltip styles for navigation icons with touch support */
.custom-tooltip {
    position: absolute;
    bottom: calc(100% + 12px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    background: rgba(0, 0, 0, 0.95);
    color: white;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    z-index: 1000;
    pointer-events: none;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    /* Prevent tooltip from interfering with other elements */
    max-width: 200px;
    text-align: center;
    line-height: 1.4;
}

.custom-tooltip.show {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

/* Ensure only one tooltip is visible at a time */
.icon-nav-btn .custom-tooltip:not(.show) {
    display: none;
}

.custom-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-top: 8px solid rgba(0, 0, 0, 0.95);
}

/* Touch-specific tooltip behavior */
@media (hover: none) and (pointer: coarse) {
    .custom-tooltip {
        display: none;
        visibility: hidden;
    }
    
    .icon-nav-btn.touch-active .custom-tooltip.show {
        display: block;
        visibility: visible;
        opacity: 1;
        transform: translateX(-50%) translateY(0);
        animation: tooltipFadeIn 0.3s ease;
    }
    
    /* Ensure no duplicate tooltips on touch devices */
    .icon-nav-btn:not(.touch-active) .custom-tooltip {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
    }
    
    /* Touch feedback for buttons */
    .icon-nav-btn:active {
        transform: scale(0.95);
        transition: transform 0.1s ease;
    }
    
    .icon-nav-btn.active:active {
        transform: scale(0.98);
    }
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Tooltip positioning adjustments for different screen sizes */
@media (max-width: 768px) {
    .custom-tooltip {
        bottom: calc(100% + 12px);
        left: 50%;
        transform: translateX(-50%);
        max-width: 200px;
        text-align: center;
        line-height: 1.4;
    }
    
    .custom-tooltip::after {
        top: 100%;
        border-top: 6px solid rgba(0, 0, 0, 0.95);
        border-bottom: none;
    }
}

@media (max-width: 480px) {
    .custom-tooltip {
        font-size: 11px;
        padding: 8px 12px;
        max-width: 160px;
        border-radius: 6px;
        bottom: calc(100% + 16px);
    }
    
    .custom-tooltip::after {
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-top: 5px solid rgba(0, 0, 0, 0.95);
    }
}

/* Enhanced icon button styles for better interaction */
.icon-nav-btn {
    position: relative;
    overflow: visible;
}

.icon-nav-btn::before {
    content: '';
    position: absolute;
    top: -4px;
    left: -4px;
    right: -4px;
    bottom: -4px;
    border-radius: 12px;
    background: transparent;
    transition: all 0.2s ease;
    z-index: -1;
}

.icon-nav-btn:hover::before {
    background: rgba(52, 152, 219, 0.1);
}

.icon-nav-btn.active::before {
    background: rgba(52, 152, 219, 0.2);
}

/* Improved focus styles for better accessibility */
.icon-nav-btn:focus {
    outline: 3px solid #3498db;
    outline-offset: 2px;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
}

.icon-nav-btn:focus:not(:focus-visible) {
    outline: none;
    box-shadow: none;
}

.icon-nav-btn:focus-visible {
    outline: 3px solid #3498db;
    outline-offset: 2px;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
}

/* Animation for state changes */
.icon-nav-btn {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.icon-nav-btn.active {
    animation: iconActivate 0.3s ease;
}

@keyframes iconActivate {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1.05);
    }
}

/* Loading state for dashboard content */
.dashboard-content {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.dashboard-content:not(.active) {
    display: none !important;
}

.dashboard-content.active {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

/* Enhanced responsive design for navigation icons */
@media (max-width: 768px) {
    .dashboard-nav-icons {
        gap: 0.5rem;
        flex-wrap: wrap;
        justify-content: center;
        margin: 0.5rem 0;
        padding: 0.5rem;
        background: rgba(248, 249, 250, 0.8);
        border-radius: 12px;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
    
    .icon-nav-btn {
        width: 44px;
        height: 44px;
        padding: 0.75rem;
        min-width: 44px;
        min-height: 44px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .icon-nav-btn i {
        font-size: 1rem;
    }
    
    /* Enhanced tooltip adjustments for mobile */
    .custom-tooltip {
        font-size: 12px;
        padding: 8px 12px;
        max-width: 220px;
        text-align: center;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    
    /* Larger touch targets for mobile accessibility */
    .icon-nav-btn::before {
        top: -12px;
        left: -12px;
        right: -12px;
        bottom: -12px;
        border-radius: 16px;
    }
    
    /* Improved hover states for touch devices */
    .icon-nav-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }
    
    .icon-nav-btn.active {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(52, 152, 219, 0.4);
    }
}

@media (max-width: 576px) {
    .dashboard-nav-icons {
        width: 100%;
        justify-content: space-evenly;
        margin: 0.75rem 0;
        gap: 0.75rem;
        padding: 0.75rem;
        border-radius: 16px;
    }
    
    .icon-nav-btn {
        width: 48px;
        height: 48px;
        padding: 0.875rem;
        min-width: 48px;
        min-height: 48px;
        border-radius: 14px;
        font-weight: 500;
    }
    
    .icon-nav-btn i {
        font-size: 1.1rem;
    }
    
    /* Optimized tooltips for small screens */
    .custom-tooltip {
        font-size: 11px;
        padding: 6px 10px;
        max-width: 180px;
        word-wrap: break-word;
        line-height: 1.3;
        border-radius: 6px;
    }
    
    /* Enhanced focus styles for small screens */
    .icon-nav-btn:focus,
    .icon-nav-btn:focus-visible {
        outline-width: 3px;
        outline-offset: 2px;
        outline-color: #3498db;
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.3);
    }
    
    /* Touch-friendly interactions */
    .icon-nav-btn:active {
        transform: translateY(0);
        transition: transform 0.1s ease;
    }
}

@media (max-width: 480px) {
    .dashboard-nav-icons {
        padding: 1rem;
        gap: 1rem;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    .icon-nav-btn {
        width: 52px;
        height: 52px;
        padding: 1rem;
        min-width: 52px;
        min-height: 52px;
        border-radius: 16px;
        border-width: 2px;
    }
    
    .icon-nav-btn i {
        font-size: 1.2rem;
    }
    
    /* Ultra-small screen tooltip optimization */
    .custom-tooltip {
        font-size: 10px;
        padding: 4px 8px;
        max-width: 140px;
        border-radius: 4px;
    }
    
    /* Improved touch target size */
    .icon-nav-btn::before {
        top: -16px;
        left: -16px;
        right: -16px;
        bottom: -16px;
        border-radius: 20px;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .icon-nav-btn {
        border-width: 2px;
    }
    
    .icon-nav-btn.active {
        background: #000;
        color: #fff;
        border-color: #000;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .icon-nav-btn,
    .icon-nav-btn i,
    .icon-nav-btn[data-tooltip="true"]:hover::after,
    .icon-nav-btn[data-tooltip="true"]:hover::before {
        transition: none;
        animation: none;
        transform: none;
    }
    
    .icon-nav-btn:hover {
        transform: none;
    }
    
    .icon-nav-btn.active {
        transform: none;
    }
    
    .icon-nav-btn.active:hover {
        transform: none;
    }
}

/* Focus visible for better keyboard navigation */
.icon-nav-btn:focus-visible {
    outline: 3px solid #3498db;
    outline-offset: 2px;
}

/* Screen reader only text */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Dashboard Tabs */
.dashboard-tabs {
    display: flex;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 4px;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}

.dashboard-tab {
    flex: 1;
    padding: 0.75rem 1rem;
    background: transparent;
    border: none;
    border-radius: 6px;
    color: #6c757d;
    font-weight: 500;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.dashboard-tab:hover {
    background: rgba(52, 152, 219, 0.1);
    color: #3498db;
}

.dashboard-tab.active {
    background: white;
    color: #2c3e50;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    font-weight: 600;
}

.dashboard-tab i {
    font-size: 0.875rem;
}

/* Dashboard Content */
.dashboard-content {
    display: none;
}

.dashboard-content.active {
    display: block;
    animation: fadeInContent 0.3s ease-in-out;
}

/* Ensure chart-card respects active state */
.chart-card .dashboard-content {
    display: none;
}

.chart-card .dashboard-content.active {
    display: flex;
}

@keyframes fadeInContent {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.chart-filter {
    margin-bottom: 1.5rem;
}

.chart-filter label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2c3e50;
    font-weight: 500;
}

.chart-filter select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #ecf0f1;
    border-radius: 8px;
    background: white;
    color: #2c3e50;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.chart-filter select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

/* Chart Filter Toggle Styles */
.chart-filter-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.chart-filter-toggle {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.filter-toggle-btn {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    border: none;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    color: white;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
    min-width: 120px;
    justify-content: center;
}

.filter-toggle-btn:hover {
    background: linear-gradient(135deg, #2980b9 0%, #1f5f8b 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
    color: white;
}

.filter-toggle-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 6px rgba(52, 152, 219, 0.3);
}

.filter-toggle-btn:focus {
    outline: 3px solid rgba(52, 152, 219, 0.3);
    outline-offset: 2px;
}

.filter-toggle-btn i {
    font-size: 0.875rem;
    transition: transform 0.3s ease;
}

.filter-toggle-btn:hover i {
    transform: scale(1.1);
}

.filter-mode-text {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
    transition: color 0.3s ease;
}

.chart-filter.status-mode .filter-mode-text {
    color: #e74c3c;
}

.chart-filter.category-mode .filter-mode-text {
    color: #3498db;
}

/* Loading state for chart */
.chart-loading {
    position: relative;
}

.chart-loading::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: 8px;
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}

.chart-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 32px;
    height: 32px;
    margin: -16px 0 0 -16px;
    border: 3px solid #ecf0f1;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    animation: chartSpin 1s linear infinite;
    z-index: 11;
}

@keyframes chartSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Smooth transitions for mode changes */
.chart-filter {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.chart-filter select {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.chart-filter.category-mode select {
    border-left: 4px solid #3498db;
}

.chart-filter.status-mode select {
    border-left: 4px solid #e74c3c;
}

/* Chart container transitions */
.chart-responsive {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.chart-responsive.updating {
    opacity: 0.7;
    transform: scale(0.98);
}

/* Filter mode text animations */
.filter-mode-text {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.filter-mode-text::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: currentColor;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.chart-filter.category-mode .filter-mode-text::before,
.chart-filter.status-mode .filter-mode-text::before {
    width: 100%;
}

/* Toggle button animations */
.filter-toggle-btn {
    position: relative;
    overflow: hidden;
}

.filter-toggle-btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.filter-toggle-btn:active::before {
    width: 100%;
    height: 100%;
}

/* Pulse animation for successful toggle */
@keyframes togglePulse {
    0% {
        box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
    }
    50% {
        box-shadow: 0 4px 16px rgba(52, 152, 219, 0.6);
    }
    100% {
        box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
    }
}

.filter-toggle-btn.pulse {
    animation: togglePulse 0.6s ease;
}

/* Chart update animation */
@keyframes chartFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.chart-responsive canvas {
    animation: chartFadeIn 0.5s ease;
}

/* Responsive adjustments for filter toggle */
@media (max-width: 768px) {
    .chart-filter-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    
    .chart-filter-toggle {
        justify-content: space-between;
    }
    
    .filter-toggle-btn {
        min-width: 100px;
        padding: 0.625rem 0.875rem;
    }
}

@media (max-width: 576px) {
    .chart-filter-header {
        gap: 1rem;
    }
    
    .chart-filter-toggle {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    
    .filter-toggle-btn {
        width: 100%;
        min-width: auto;
    }
    
    .filter-mode-text {
        text-align: center;
        font-size: 0.9rem;
    }
}

/* Future dashboard styles */
.dashboard-placeholder {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}

.dashboard-placeholder i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.dashboard-placeholder h4 {
    margin-bottom: 0.5rem;
    color: #495057;
}

.dashboard-placeholder p {
    margin: 0;
    font-size: 0.9rem;
}

/* Activity Card - Correções para altura responsiva adequada */
.activity-card {
    grid-column: span 1;
    display: flex;
    flex-direction: column;
    height: fit-content; /* Altura baseada no conteúdo disponível */
    max-height: 800px; /* Altura máxima para evitar containers muito altos */
    min-height: 500px; /* Altura mínima consistente */
}

/* Melhorias específicas para alinhamento de altura */
.content-grid .modern-card {
    align-self: start; /* Alinha cards no topo ao invés de esticar */
    justify-self: stretch; /* Mantém largura total do grid */
}

/* Correções específicas para o container de atividades recentes */
.activity-card .modern-card {
    height: fit-content; /* Altura baseada no conteúdo disponível */
    max-height: 100%; /* Não excede o container pai */
    min-height: auto; /* Remove altura mínima forçada */
    display: flex;
    flex-direction: column;
}

.activity-card .modern-card-body {
    flex: 1; /* Ocupa todo o espaço disponível */
    display: flex;
    flex-direction: column;
    min-height: 0; /* Permite que o conteúdo encolha */
    padding: 1.5rem; /* Padding consistente */
}

/* Garante que o conteúdo da tabela se ajuste adequadamente */
.activity-card .table-responsive {
    flex: 1; /* Ocupa o espaço disponível */
    max-height: none; /* Remove limitação de altura fixa */
    overflow-y: auto; /* Scroll quando necessário */
    margin-bottom: 1rem; /* Espaçamento antes do botão */
}

/* Correção para distribuição de espaço na tabela de atividades recentes */
.activity-card .modern-table {
    height: fit-content; /* Altura baseada no conteúdo */
    margin-bottom: 0; /* Remove margem inferior desnecessária */
}

.activity-card .modern-table tbody {
    height: fit-content; /* Altura baseada no conteúdo */
}

/* Elimina "buracos vazios" no container lateral */
.activity-card .text-center {
    margin-top: auto; /* Empurra o botão para o final */
    flex-shrink: 0; /* Impede que o botão encolha */
    padding-top: 0; /* Remove padding superior desnecessário */
}

/* Remove elementos vazios que podem causar espaçamento */
.activity-card .modern-card-body > div:empty {
    display: none !important;
}

/* Garante que não há elementos invisíveis ocupando espaço */
.activity-card [style*="display: none"] {
    position: absolute !important;
    top: -9999px !important;
    left: -9999px !important;
}

/* Melhora a distribuição do espaço no card de análises */
.chart-card .modern-card-body {
    min-height: 500px;
}

.chart-card .dashboard-content.active {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.chart-card .chart-responsive {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 300px;
}

/* Ajustes para o filtro do gráfico */
.chart-card .chart-filter {
    flex-shrink: 0;
    margin-bottom: 1rem;
}

/* Correções específicas para o container #asset-trends */
.chart-card #asset-trends {
    display: flex;
    flex-direction: column;
    height: fit-content; /* Altura baseada no conteúdo */
    min-height: 400px; /* Altura mínima para consistência */
    justify-content: flex-start; /* Alinha conteúdo no topo */
    flex: 1; /* Ocupa espaço disponível no container pai */
    margin: 0; /* Remove margens desnecessárias que causam espaçamento */
    padding: 0; /* Remove padding desnecessário */
}

/* Correção específica para .modern-card dentro de #asset-trends */
.chart-card #asset-trends .modern-card {
    height: fit-content; /* Altura baseada no conteúdo ao invés de altura fixa */
    min-height: auto; /* Remove altura mínima forçada */
    margin: 0; /* Elimina margens desnecessárias que causam espaçamento */
    flex: 1; /* Ocupa espaço disponível */
    align-self: start; /* Alinha no topo do container */
}

.chart-card #asset-trends .maintenances-section {
    display: flex;
    flex-direction: column;
    height: 100%;
    margin: 0; /* Remove margens desnecessárias */
    padding: 0; /* Remove padding desnecessário */
}

.chart-card #asset-trends .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    flex-shrink: 0; /* Impede que o header encolha */
}

.chart-card #asset-trends .section-title {
    margin: 0;
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.1rem;
}

.chart-card #asset-trends .section-content {
    flex: 1; /* Ocupa todo o espaço restante */
    display: flex;
    flex-direction: column;
    min-height: 0; /* Permite que o conteúdo encolha */
}

/* Remove elementos órfãos e vazios */
.chart-card #asset-trends .section-content > div:empty {
    display: none !important;
}

/* Garante que não há elementos invisíveis ocupando espaço */
.chart-card #asset-trends [style*="display: none"] {
    position: absolute !important;
    top: -9999px !important;
    left: -9999px !important;
}

/* Otimizações adicionais para o CSS Grid */
.content-grid > * {
    min-width: 0; /* Evita overflow em containers filhos */
    min-height: 0; /* Permite que containers encolham conforme necessário */
}

/* Implementa height: fit-content para containers baseados em conteúdo */
.chart-card #asset-trends .section-content,
.chart-card #asset-trends .maintenances-section,
.chart-card #asset-trends .livewire-component {
    height: fit-content; /* Altura baseada no conteúdo */
    min-height: 0; /* Remove altura mínima forçada */
    margin: 0; /* Elimina margens desnecessárias */
}

/* Garante que elementos filhos também sigam o padrão fit-content */
.chart-card #asset-trends .table-responsive,
.chart-card #asset-trends .pagination-wrapper {
    height: fit-content; /* Altura baseada no conteúdo */
    margin-bottom: 0; /* Remove margens inferiores desnecessárias */
}

/* Correções específicas para o componente Livewire OpenMaintenancesTable */
.chart-card #asset-trends .open-maintenances-container {
    height: fit-content; /* Altura baseada no conteúdo */
    min-height: auto; /* Remove altura mínima forçada */
    margin: 0; /* Elimina margens desnecessárias */
    padding: 0; /* Remove padding desnecessário */
}

/* Garante que não há elementos vazios ou órfãos no componente Livewire */
.chart-card #asset-trends .open-maintenances-container > div:empty,
.chart-card #asset-trends .open-maintenances-container > span:empty {
    display: none !important;
}

/* Remove espaçamentos desnecessários da tabela de manutenções */
.chart-card #asset-trends .open-maintenances-container .table-responsive {
    margin: 0; /* Remove margens */
    padding: 0; /* Remove padding */
}

.chart-card #asset-trends .open-maintenances-container .pagination-container {
    margin-top: 0.5rem; /* Reduz margem superior da paginação */
    margin-bottom: 0; /* Remove margem inferior */
}

/* Garante que o container de análises não force altura desnecessária */
.chart-card {
    display: flex;
    flex-direction: column;
    overflow: hidden; /* Evita que conteúdo vaze do container */
}

.chart-card .modern-card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0; /* Permite flexibilidade na altura */
}

/* Correções responsivas para o container de atividades recentes */
@media (max-width: 1200px) {
    .activity-card {
        min-height: 450px; /* Altura mínima reduzida para telas menores */
        max-height: 700px; /* Altura máxima reduzida */
    }
    
    .activity-card .modern-card-body {
        padding: 1.25rem; /* Padding reduzido */
    }
}

@media (max-width: 992px) {
    .content-grid {
        grid-template-columns: 1fr; /* Layout em coluna única */
        gap: 1.5rem;
    }
    
    .activity-card {
        min-height: 400px; /* Altura mínima ainda menor */
        max-height: 600px; /* Altura máxima reduzida */
    }
    
    .activity-card .table-responsive {
        max-height: 350px; /* Limita altura da tabela em telas menores */
    }
}

@media (max-width: 768px) {
    .activity-card {
        min-height: 350px; /* Altura mínima para mobile */
        max-height: 500px; /* Altura máxima para mobile */
    }
    
    .activity-card .modern-card-body {
        padding: 1rem; /* Padding reduzido para mobile */
    }
    
    .activity-card .table-responsive {
        max-height: 300px; /* Altura máxima da tabela em mobile */
    }
}

@media (max-width: 576px) {
    .activity-card {
        min-height: 300px; /* Altura mínima muito reduzida */
        max-height: 400px; /* Altura máxima muito reduzida */
    }
    
    .activity-card .modern-card-body {
        padding: 0.75rem; /* Padding mínimo */
    }
    
    .activity-card .table-responsive {
        max-height: 250px; /* Altura máxima muito reduzida */
    }
}

/* Bottom Grid */
.bottom-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

/* Table Improvements */
.modern-table {
    border-radius: 8px;
    overflow: hidden;
    border: none;
}

.modern-table thead th {
    background: #f8f9fa;
    border: none;
    padding: 1rem;
    font-weight: 600;
    color: #2c3e50;
}

.modern-table tbody td {
    padding: 1rem;
    border-top: 1px solid #ecf0f1;
    vertical-align: middle;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 2rem;
    background: #ecf0f1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #95a5a6;
}

.empty-state h3 {
    color: #2c3e50;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #7f8c8d;
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 2rem;
}

.action-btn {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    border: none;
    color: white;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    text-align: center;
}

.action-btn:hover {
    transform: translateY(-2px);
    color: white;
    text-decoration: none;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr 1fr; /* Ajusta proporção em telas médias */
        gap: 1.5rem;
        align-items: start; /* Mantém alinhamento no topo */
    }
    
    .modern-card {
        min-height: 450px; /* Reduz altura mínima em telas médias */
        height: fit-content; /* Mantém altura baseada no conteúdo */
    }
    
    .activity-card .modern-card-body {
        min-height: 400px;
    }
}

@media (max-width: 992px) {
    .content-grid {
        grid-template-columns: 1fr; /* Stack vertical em tablets */
        gap: 1.5rem;
        align-items: start; /* Mantém alinhamento no topo */
        min-height: auto; /* Remove altura mínima fixa em tablets */
    }
    
    .modern-card {
        min-height: 350px; /* Altura mínima reduzida para tablets */
        height: fit-content; /* Altura baseada no conteúdo */
        max-height: none; /* Remove limite máximo em tablets */
    }
    
    .activity-card .modern-card-body {
        min-height: 300px; /* Reduz altura mínima em tablets */
    }
}

@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        align-items: start; /* Mantém alinhamento no topo */
        min-height: auto; /* Remove altura mínima em mobile */
    }
    
    .bottom-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .modern-card {
        min-height: auto; /* Remove altura mínima em mobile */
        height: auto;
    }
    
    .modern-card-header,
    .modern-card-body {
        padding: 1rem;
    }
    
    .modern-card-header {
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .dashboard-nav-icons {
        order: 2;
        width: 100%;
        justify-content: center;
    }
    
    .modern-card-title {
        order: 1;
        flex: 1;
    }
    
    .collapse-btn {
        order: 3;
    }
    
    .activity-card .modern-card-body {
        min-height: 300px; /* Altura mínima reduzida para mobile */
    }
}

@media (max-width: 576px) {
    .content-grid {
        gap: 0.75rem;
        align-items: start; /* Mantém alinhamento no topo */
        min-height: auto; /* Remove altura mínima em telas muito pequenas */
    }
    
    .modern-card-header,
    .modern-card-body {
        padding: 0.75rem;
    }
    
    .activity-card .modern-card-body {
        min-height: 250px; /* Altura ainda menor para telas pequenas */
    }
    
    .modern-card-body {
        padding: 1rem 0.75rem; /* Ajusta padding interno */
    }
}

/* Collapse functionality */
.modern-card.collapsed {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.modern-card.collapsed .modern-card-body {
    display: none;
}

.modern-card.collapsed {
    min-height: auto;
    height: auto;
}

.collapse-btn {
    transition: all 0.3s ease;
    border: 1px solid #dee2e6;
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.collapse-btn:hover {
    background-color: #f8f9fa;
    border-color: #adb5bd;
    transform: scale(1.05);
}

.collapse-icon {
    transition: transform 0.3s ease;
    font-size: 0.875rem;
}

.collapse-btn.collapsed .collapse-icon {
    transform: rotate(180deg);
}

/* Smooth height transition for cards */
.modern-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.modern-card.collapsing {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.modern-card-body {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Enhanced collapse animation */
.modern-card.collapsed .modern-card-header {
    border-bottom: none;
}

.modern-card-header {
    transition: border-bottom 0.3s ease;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        max-height: 1000px;
        opacity: 1;
    }
    to {
        max-height: 0;
        opacity: 0;
    }
}

@keyframes slideDown {
    from {
        max-height: 0;
        opacity: 0;
    }
    to {
        max-height: 1000px;
        opacity: 1;
    }
}

.stat-card {
    animation: fadeInUp 0.6s ease forwards;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }
.stat-card:nth-child(5) { animation-delay: 0.5s; }
.stat-card:nth-child(6) { animation-delay: 0.6s; }
</style>
@endpush

{{-- Enhanced JavaScript for touch and mobile interactions --}}
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced tooltip management system to prevent duplicates
    class TooltipManager {
        constructor() {
            this.activeTooltip = null;
            this.tooltipTimeout = null;
            this.tooltips = new Map();
            this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        }
        
        // Create tooltip for a button
        createTooltip(button) {
            // Check if tooltip already exists
            if (this.tooltips.has(button)) {
                return this.tooltips.get(button);
            }
            
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = button.getAttribute('title') || button.getAttribute('data-tooltip');
            
            // Position tooltip relative to button
            button.style.position = 'relative';
            button.appendChild(tooltip);
            
            // Store reference
            this.tooltips.set(button, tooltip);
            return tooltip;
        }
        
        // Show tooltip and hide any active ones
        showTooltip(button, tooltip) {
            // Hide any currently active tooltip
            this.hideActiveTooltip();
            
            // Clear any pending timeout
            clearTimeout(this.tooltipTimeout);
            
            // Show new tooltip
            tooltip.classList.add('show');
            this.activeTooltip = tooltip;
            
            // For touch devices, add touch-active class
            if (this.isTouchDevice) {
                button.classList.add('touch-active');
            }
        }
        
        // Hide the currently active tooltip
        hideActiveTooltip() {
            if (this.activeTooltip) {
                this.activeTooltip.classList.remove('show');
                
                // Remove touch-active class from parent button
                const parentButton = this.activeTooltip.parentElement;
                if (parentButton) {
                    parentButton.classList.remove('touch-active');
                }
                
                this.activeTooltip = null;
            }
        }
        
        // Hide tooltip with optional delay
        hideTooltip(tooltip, delay = 0) {
            if (delay > 0) {
                clearTimeout(this.tooltipTimeout);
                this.tooltipTimeout = setTimeout(() => {
                    if (this.activeTooltip === tooltip) {
                        this.hideActiveTooltip();
                    }
                }, delay);
            } else {
                if (this.activeTooltip === tooltip) {
                    this.hideActiveTooltip();
                }
            }
        }
        
        // Clean up all tooltips
        cleanup() {
            this.hideActiveTooltip();
            clearTimeout(this.tooltipTimeout);
            this.tooltips.clear();
        }
    }
    
    // Initialize tooltip manager
    const tooltipManager = new TooltipManager();
    
    // Enhanced tooltip and navigation functionality
    const iconButtons = document.querySelectorAll('.icon-nav-btn');
    
    iconButtons.forEach(button => {
        // Create tooltip element using manager
        const tooltip = tooltipManager.createTooltip(button);
        
        if (tooltipManager.isTouchDevice) {
            // Touch device behavior
            button.addEventListener('touchstart', function(e) {
                e.preventDefault();
                
                // Show tooltip using manager (automatically hides others)
                tooltipManager.showTooltip(button, tooltip);
                
                // Auto-hide after 2 seconds
                tooltipManager.hideTooltip(tooltip, 2000);
            });
            
            button.addEventListener('touchend', function(e) {
                // Handle navigation on touch end
                setTimeout(() => {
                    const dashboardType = button.getAttribute('data-dashboard');
                    if (dashboardType) {
                        switchDashboard(dashboardType, button);
                    }
                }, 100);
            });
            
        } else {
            // Desktop behavior with improved tooltip management
            button.addEventListener('mouseenter', function() {
                tooltipManager.showTooltip(button, tooltip);
            });
            
            button.addEventListener('mouseleave', function() {
                tooltipManager.hideTooltip(tooltip);
            });
            
            button.addEventListener('click', function(e) {
                e.preventDefault();
                // Hide tooltip immediately on click
                tooltipManager.hideActiveTooltip();
                
                const dashboardType = button.getAttribute('data-dashboard');
                if (dashboardType) {
                    switchDashboard(dashboardType, button);
                }
            });
        }
        
        // Keyboard navigation support
        button.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                // Hide tooltip on keyboard activation
                tooltipManager.hideActiveTooltip();
                
                const dashboardType = button.getAttribute('data-dashboard');
                if (dashboardType) {
                    switchDashboard(dashboardType, button);
                }
            }
        });
        
        // Focus events for keyboard navigation
        button.addEventListener('focus', function() {
            if (!tooltipManager.isTouchDevice) {
                tooltipManager.showTooltip(button, tooltip);
            }
        });
        
        button.addEventListener('blur', function() {
            tooltipManager.hideTooltip(tooltip);
        });
    });
    
    // Hide tooltips when clicking outside or on window events
    document.addEventListener('touchstart', function(e) {
        if (!e.target.closest('.icon-nav-btn')) {
            tooltipManager.hideActiveTooltip();
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.icon-nav-btn')) {
            tooltipManager.hideActiveTooltip();
        }
    });
    
    // Hide tooltips on scroll or resize
    window.addEventListener('scroll', function() {
        tooltipManager.hideActiveTooltip();
    });
    
    window.addEventListener('resize', function() {
        tooltipManager.hideActiveTooltip();
    });
    
    // Dashboard switching functionality
    function switchDashboard(type, activeButton) {
        // Hide all tooltips during navigation
        tooltipManager.hideActiveTooltip();
        
        // Remove active class from all buttons
        iconButtons.forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-selected', 'false');
            btn.setAttribute('tabindex', '-1');
        });
        
        // Add active class to clicked button
        activeButton.classList.add('active');
        activeButton.setAttribute('aria-selected', 'true');
        activeButton.setAttribute('tabindex', '0');
        
        // Hide all dashboard content
        const dashboardContents = document.querySelectorAll('.dashboard-content');
        dashboardContents.forEach(content => {
            content.classList.remove('active');
        });
        
        // Show selected dashboard content
        const targetContent = document.getElementById(type);
        if (targetContent) {
            targetContent.classList.add('active');
        }
        
        // Announce change to screen readers
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.textContent = `Switched to ${activeButton.getAttribute('title')} view`;
        document.body.appendChild(announcement);
        
        setTimeout(() => {
            document.body.removeChild(announcement);
        }, 1000);
    }
    
    // Responsive behavior adjustments
    function handleResize() {
        const width = window.innerWidth;
        const navIcons = document.querySelector('.dashboard-nav-icons');
        
        if (width <= 480) {
            navIcons?.classList.add('mobile-layout');
        } else {
            navIcons?.classList.remove('mobile-layout');
        }
        
        // Hide tooltips on resize to prevent positioning issues
        tooltipManager.hideActiveTooltip();
    }
    
    // Handle window resize with debouncing
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(handleResize, 150);
    });
    
    // Initial setup
    handleResize();
    
    // Improve accessibility for screen readers
    const navContainer = document.querySelector('.dashboard-nav-icons');
    if (navContainer) {
        navContainer.setAttribute('role', 'tablist');
        navContainer.setAttribute('aria-label', 'Dashboard navigation');
    }
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        tooltipManager.cleanup();
    });
    
    // Handle visibility change (tab switching)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            tooltipManager.hideActiveTooltip();
        }
    });
});
</script>
@endpush

{{-- Page content --}}
@section('content')
<div class="modern-dashboard">
    <div class="container-fluid">
        <div class="dashboard-header">
            <h1>{{ trans('general.dashboard') }}</h1>
            <div class="subtitle">
                @if ($snipeSettings->dashboard_message!='')
                    {!! Helper::parseEscapedMarkedown($snipeSettings->dashboard_message) !!}
                @else
                    {{ trans('general.dashboard_info') }}
                @endif
            </div>
        </div>

        <!-- Modern Stats Grid -->
        <div class="stats-grid">
            <a href="{{ route('hardware.index') }}" class="stat-card assets">
                <div class="stat-card-content">
                    <div class="stat-card-info">
                        <h3>{{ number_format(\App\Models\Asset::AssetsForShow()->count()) }}</h3>
                        <p>{{ trans('general.assets') }}</p>
                    </div>
                    <div class="stat-card-icon">
                        <x-icon type="assets" />
                    </div>
                </div>
                <div class="stat-card-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right" />
                </div>
            </a>

            <a href="{{ route('licenses.index') }}" class="stat-card licenses">
                <div class="stat-card-content">
                    <div class="stat-card-info">
                        <h3>{{ number_format($counts['license']) }}</h3>
                        <p>{{ trans('general.licenses') }}</p>
                    </div>
                    <div class="stat-card-icon">
                        <x-icon type="licenses" />
                    </div>
                </div>
                <div class="stat-card-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right" />
                </div>
            </a>

            <a href="{{ route('accessories.index') }}" class="stat-card accessories">
                <div class="stat-card-content">
                    <div class="stat-card-info">
                        <h3>{{ number_format($counts['accessory']) }}</h3>
                        <p>{{ trans('general.accessories') }}</p>
                    </div>
                    <div class="stat-card-icon">
                        <x-icon type="accessories" />
                    </div>
                </div>
                <div class="stat-card-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right" />
                </div>
            </a>

            <a href="{{ route('consumables.index') }}" class="stat-card consumables">
                <div class="stat-card-content">
                    <div class="stat-card-info">
                        <h3>{{ number_format($counts['consumable']) }}</h3>
                        <p>{{ trans('general.consumables') }}</p>
                    </div>
                    <div class="stat-card-icon">
                        <x-icon type="consumables" />
                    </div>
                </div>
                <div class="stat-card-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right" />
                </div>
            </a>

            <a href="{{ route('components.index') }}" class="stat-card components">
                <div class="stat-card-content">
                    <div class="stat-card-info">
                        <h3>{{ number_format($counts['component']) }}</h3>
                        <p>{{ trans('general.components') }}</p>
                    </div>
                    <div class="stat-card-icon">
                        <x-icon type="components" />
                    </div>
                </div>
                <div class="stat-card-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right" />
                </div>
            </a>

            <a href="{{ route('users.index') }}" class="stat-card users">
                <div class="stat-card-content">
                    <div class="stat-card-info">
                        <h3>{{ number_format($counts['user']) }}</h3>
                        <p>{{ trans('general.people') }}</p>
                    </div>
                    <div class="stat-card-icon">
                        <x-icon type="users" />
                    </div>
                </div>
                <div class="stat-card-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right" />
                </div>
            </a>
        </div>

        @if ($counts['grand_total'] == 0)
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <x-icon type="assets" />
            </div>
            <h3>{{ trans('general.dashboard_info') }}</h3>
            <p>{{ trans('general.dashboard_empty') }}</p>
            
            <div class="action-buttons">
                @can('create', \App\Models\Asset::class)
                <a href="{{ route('hardware.create') }}" class="action-btn" style="background: #1abc9c;">
                    <x-icon type="assets" /> {{ trans('general.new_asset') }}
                </a>
                @endcan
                @can('create', \App\Models\License::class)
                <a href="{{ route('licenses.create') }}" class="action-btn" style="background: #e74c3c;">
                    <x-icon type="licenses" /> {{ trans('general.new_license') }}
                </a>
                @endcan
                @can('create', \App\Models\Accessory::class)
                <a href="{{ route('accessories.create') }}" class="action-btn" style="background: #f39c12;">
                    <x-icon type="accessories" /> {{ trans('general.new_accessory') }}
                </a>
                @endcan
                @can('create', \App\Models\Consumable::class)
                <a href="{{ route('consumables.create') }}" class="action-btn" style="background: #9b59b6;">
                    <x-icon type="consumables" /> {{ trans('general.new_consumable') }}
                </a>
                @endcan
                @can('create', \App\Models\Component::class)
                <a href="{{ route('components.create') }}" class="action-btn" style="background: #f1c40f;">
                    <x-icon type="components" /> {{ trans('general.new_component') }}
                </a>
                @endcan
                @can('create', \App\Models\User::class)
                <a href="{{ route('users.create') }}" class="action-btn" style="background: #3498db;">
                    <x-icon type="users" /> {{ trans('general.new_user') }}
                </a>
                @endcan
            </div>
        </div>
        @else

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Recent Activity Card -->
            <div class="modern-card activity-card">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <x-icon type="activity" style="margin-right: 0.5rem;" />
                        {{ trans('general.recent_activity') }}
                    </h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary collapse-btn" data-target="activity-card">
                        <x-icon type="minus" class="collapse-icon" />
                    </button>
                </div>
                <div class="modern-card-body" id="activity-card">
                    <div class="table-responsive">
                        <table
                            data-cookie-id-table="dashActivityReport"
                            data-height="500"
                            data-pagination="false"
                            data-side-pagination="server"
                            data-id-table="dashActivityReport"
                            data-sort-order="desc"
                            data-sort-name="created_at"
                            id="dashActivityReport"
                            class="table table-striped snipe-table modern-table"
                            data-url="{{ route('api.activity.index', ['limit' => 25]) }}">
                            <thead>
                            <tr>
                                <th data-field="icon" data-visible="true" style="width: 40px;" class="hidden-xs" data-formatter="iconFormatter">
                                    <span class="sr-only">{{ trans('admin/hardware/table.icon') }}</span>
                                </th>
                                <th class="col-sm-3" data-visible="true" data-field="created_at" data-formatter="dateDisplayFormatter">
                                    {{ trans('general.date') }}
                                </th>
                                <th class="col-sm-2" data-visible="true" data-field="admin" data-formatter="usersLinkObjFormatter">
                                    {{ trans('general.created_by') }}
                                </th>
                                <th class="col-sm-2" data-visible="true" data-field="action_type">
                                    {{ trans('general.action') }}
                                </th>
                                <th class="col-sm-3" data-visible="true" data-field="item" data-formatter="polymorphicItemFormatter">
                                    {{ trans('general.item') }}
                                </th>
                                <th class="col-sm-2" data-visible="true" data-field="target" data-formatter="polymorphicItemFormatter">
                                    {{ trans('general.target') }}
                                </th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                    <div class="text-center" style="margin-top: 1.5rem;">
                        <a href="{{ route('reports.activity') }}" class="btn btn-primary">
                            {{ trans('general.viewall') }}
                            <x-icon type="arrow-circle-right" style="margin-left: 0.5rem;" />
                        </a>
                    </div>
                </div>
            </div>

            <!-- Analytics Dashboard Card -->
            <div class="modern-card chart-card">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <x-icon type="chart-pie" style="margin-right: 0.5rem;" />
                        {{ trans('general.analytics') }}
                    </h3>
                    
                    <!-- Dashboard Navigation Icons -->
                    <div class="dashboard-nav-icons" role="tablist" aria-label="{{ trans('general.analytics') }} {{ trans('general.navigation') }}">
                        <button class="icon-nav-btn active" 
                                data-dashboard="status-overview" 
                                data-tooltip="true" 
                                title="{{ trans('general.status_overview') }}"
                                role="tab"
                                aria-selected="true"
                                aria-controls="status-overview"
                                tabindex="0">
                            <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                            <span class="sr-only">{{ trans('general.status_overview') }}</span>
                        </button>
                        
                        <button class="icon-nav-btn" 
                                data-dashboard="asset-trends" 
                                data-tooltip="true" 
                                title="{{ trans('general.maintenances') }}"
                                role="tab"
                                aria-selected="false"
                                aria-controls="asset-trends"
                                tabindex="-1">
                            <i class="fas fa-wrench" aria-hidden="true"></i>
                            <span class="sr-only">{{ trans('general.maintenances') }}</span>
                        </button>
                        
                        <button class="icon-nav-btn" 
                                data-dashboard="performance-metrics" 
                                data-tooltip="true" 
                                title="{{ trans('general.performance_metrics') }}"
                                role="tab"
                                aria-selected="false"
                                aria-controls="performance-metrics"
                                tabindex="-1">
                            <i class="fas fa-chart-bar" aria-hidden="true"></i>
                            <span class="sr-only">{{ trans('general.performance_metrics') }}</span>
                        </button>
                    </div>
                    
                    <button type="button" class="btn btn-sm btn-outline-secondary collapse-btn" data-target="chart-card">
                        <x-icon type="minus" class="collapse-icon" />
                    </button>
                </div>
                <div class="modern-card-body" id="chart-card">
                    <!-- Dashboard Tabs (Hidden - functionality maintained via icons) -->
                    <div class="dashboard-tabs" style="display: none;">
                        <button class="dashboard-tab active" data-dashboard="status-overview">
                            <x-icon type="chart-pie" />
                            {{ trans('general.status_overview') }}
                        </button>
                        <button class="dashboard-tab" data-dashboard="asset-trends">
                            <x-icon type="wrench" />
                            {{ trans('general.maintenances') }}
                        </button>
                        <button class="dashboard-tab" data-dashboard="performance-metrics">
                            <x-icon type="chart-bar" />
                            {{ trans('general.performance_metrics') }}
                        </button>
                    </div>

                    <!-- Status Overview Dashboard -->
                    <div class="dashboard-content active" id="status-overview">
                        <div class="chart-filter category-mode" id="chartFilterContainer">
                            <div class="chart-filter-header">
                                <div class="chart-filter-toggle">
                                    <span class="filter-mode-text" id="filterModeText">{{ trans('general.filter_by_category') }}</span>
                                    <button type="button" class="filter-toggle-btn" id="filterToggleBtn" title="{{ trans('general.toggle_filter_mode') }}">
                                        <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                                        <span class="sr-only">{{ trans('general.toggle_filter_mode') }}</span>
                                    </button>
                                </div>
                            </div>
                            <select id="categoryFilter" class="form-control">
                                <option value="all">{{ trans('general.all_categories') }}</option>
                            </select>
                        </div>
                        <div class="chart-responsive" id="chartContainer">
                            <canvas id="statusPieChart" height="260"></canvas>
                        </div>
                    </div>

                    <!-- Asset Trends Dashboard -->
                    <div class="dashboard-content" id="asset-trends">
                        @can('view', \App\Models\Maintenance::class)
                            <!-- Manutenções em Aberto Section -->
                            <div class="maintenances-section">
                                <div class="section-header">
                                    <h4 class="section-title">
                                        <x-icon type="wrench" style="margin-right: 0.5rem;" />
                                        {{ trans('admin/maintenances/general.open_maintenances') }}
                                    </h4>
                                    <a href="{{ route('maintenances.index') }}" class="btn btn-sm btn-outline-primary" style="border-radius: 8px;">
                                        {{ trans('admin/maintenances/general.view_all_maintenances') }}
                                        <x-icon type="arrow-circle-right" style="margin-left: 0.25rem;" />
                                    </a>
                                </div>
                                <div class="section-content">
                                    @livewire('open-maintenances-table')
                                </div>
                            </div>
                        @else
                            <div class="dashboard-placeholder">
                                <x-icon type="lock" />
                                <h4>{{ trans('general.insufficient_permissions') }}</h4>
                                <p>{{ trans('general.action_permission_generic', ['action' => trans('general.view'), 'item_type' => trans('admin/maintenances/general.maintenances')]) }}</p>
                            </div>
                        @endcan
                    </div>

                    <!-- Performance Metrics Dashboard (Placeholder) -->
                    <div class="dashboard-content" id="performance-metrics">
                        <div class="dashboard-placeholder">
                            <x-icon type="chart-bar" />
                            <h4>{{ trans('general.performance_metrics') }}</h4>
                            <p>{{ trans('general.dashboard_coming_soon') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Bottom Grid -->
        <div class="bottom-grid">
            @if ((($snipeSettings->scope_locations_fmcs!='1') && ($snipeSettings->full_multiple_companies_support=='1')))
            <!-- Companies Card -->
            <div class="modern-card">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <x-icon type="building" style="margin-right: 0.5rem;" />
                        {{ trans('general.companies') }}
                    </h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary collapse-btn" data-target="companies-card">
                        <x-icon type="minus" class="collapse-icon" />
                    </button>
                </div>
                <div class="modern-card-body" id="companies-card">
                    <div class="table-responsive">
                        <table
                            data-cookie-id-table="dashCompanySummary"
                            data-height="400"
                            data-pagination="false"
                            data-side-pagination="server"
                            data-sort-order="desc"
                            data-sort-field="assets_count"
                            id="dashCompanySummary"
                            class="table table-striped snipe-table modern-table"
                            data-url="{{ route('api.companies.index', ['sort' => 'assets_count', 'order' => 'asc']) }}">
                            <thead>
                            <tr>
                                <th class="col-sm-3" data-visible="true" data-field="name" data-formatter="companiesLinkFormatter" data-sortable="true">
                                    {{ trans('general.name') }}
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="users_count" data-sortable="true">
                                    <x-icon type="users" />
                                    <span class="sr-only">{{ trans('general.people') }}</span>
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="assets_count" data-sortable="true">
                                    <x-icon type="assets" />
                                    <span class="sr-only">{{ trans('general.asset_count') }}</span>
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="accessories_count" data-sortable="true">
                                    <x-icon type="accessories" />
                                    <span class="sr-only">{{ trans('general.accessories_count') }}</span>
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="consumables_count" data-sortable="true">
                                    <x-icon type="consumables" />
                                    <span class="sr-only">{{ trans('general.consumables_count') }}</span>
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="components_count" data-sortable="true">
                                    <x-icon type="components" />
                                    <span class="sr-only">{{ trans('general.components_count') }}</span>
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="licenses_count" data-sortable="true">
                                    <x-icon type="licenses" />
                                    <span class="sr-only">{{ trans('general.licenses_count') }}</span>
                                </th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                    <div class="text-center" style="margin-top: 1.5rem;">
                        <a href="{{ route('companies.index') }}" class="btn btn-primary">
                            {{ trans('general.viewall') }}
                            <x-icon type="arrow-circle-right" style="margin-left: 0.5rem;" />
                        </a>
                    </div>
                </div>
            </div>
            @else
            <!-- Locations Card -->
            <div class="modern-card">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <x-icon type="location" style="margin-right: 0.5rem;" />
                        {{ trans('general.locations') }}
                    </h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary collapse-btn" data-target="locations-card">
                        <x-icon type="minus" class="collapse-icon" />
                    </button>
                </div>
                <div class="modern-card-body" id="locations-card">
                    <div class="table-responsive">
                        <table
                            data-cookie-id-table="dashLocationSummary"
                            data-height="400"
                            data-side-pagination="server"
                            data-pagination="false"
                            data-sort-order="desc"
                            data-sort-field="assets_count"
                            id="dashLocationSummary"
                            class="table table-striped snipe-table modern-table"
                            data-url="{{ route('api.locations.index', ['sort' => 'assets_count', 'order' => 'asc']) }}">
                            <thead>
                            <tr>
                                <th class="col-sm-3" data-visible="true" data-field="name" data-formatter="locationsLinkFormatter" data-sortable="true">
                                    {{ trans('general.name') }}
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="assets_count" data-sortable="true">
                                    <x-icon type="assets" />
                                    <span class="sr-only">{{ trans('general.asset_count') }}</span>
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="assigned_assets_count" data-sortable="true">
                                    {{ trans('general.assigned') }}
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="users_count" data-sortable="true">
                                    <x-icon type="users" />
                                    <span class="sr-only">{{ trans('general.people') }}</span>
                                </th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                    <div class="text-center" style="margin-top: 1.5rem;">
                        <a href="{{ route('locations.index') }}" class="btn btn-primary">
                            {{ trans('general.viewall') }}
                            <x-icon type="arrow-circle-right" style="margin-left: 0.5rem;" />
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <!-- Categories Card -->
            <div class="modern-card">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <x-icon type="categories" style="margin-right: 0.5rem;" />
                        {{ trans('general.asset') }} {{ trans('general.categories') }}
                    </h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary collapse-btn" data-target="categories-card">
                        <x-icon type="minus" class="collapse-icon" />
                    </button>
                </div>
                <div class="modern-card-body" id="categories-card">
                    <div class="table-responsive">
                        <table
                            data-cookie-id-table="dashCategorySummary"
                            data-height="400"
                            data-pagination="false"
                            data-side-pagination="server"
                            data-sort-order="desc"
                            data-sort-field="assets_count"
                            id="dashCategorySummary"
                            class="table table-striped snipe-table modern-table"
                            data-url="{{ route('api.categories.index', ['sort' => 'assets_count', 'order' => 'asc']) }}">
                            <thead>
                            <tr>
                                <th class="col-sm-3" data-visible="true" data-field="name" data-formatter="categoriesLinkFormatter" data-sortable="true">
                                    {{ trans('general.name') }}
                                </th>
                                <th class="col-sm-3" data-visible="true" data-field="category_type" data-sortable="true">
                                    {{ trans('general.type') }}
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="assets_count" data-sortable="true">
                                    <x-icon type="assets" />
                                    <span class="sr-only">{{ trans('general.asset_count') }}</span>
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="accessories_count" data-sortable="true">
                                    <x-icon type="accessories" />
                                    <span class="sr-only">{{ trans('general.accessories_count') }}</span>
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="consumables_count" data-sortable="true">
                                    <x-icon type="consumables" />
                                    <span class="sr-only">{{ trans('general.consumables_count') }}</span>
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="components_count" data-sortable="true">
                                    <x-icon type="components" />
                                    <span class="sr-only">{{ trans('general.components_count') }}</span>
                                </th>
                                <th class="col-sm-1" data-visible="true" data-field="licenses_count" data-sortable="true">
                                    <x-icon type="licenses" />
                                    <span class="sr-only">{{ trans('general.licenses_count') }}</span>
                                </th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                    <div class="text-center" style="margin-top: 1.5rem;">
                        <a href="{{ route('categories.index') }}" class="btn btn-primary">
                            {{ trans('general.viewall') }}
                            <x-icon type="arrow-circle-right" style="margin-left: 0.5rem;" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>


@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['simple_view' => true, 'nopages' => true])
@stop

@push('js')
        <script src="{{ asset('js/layout-diagnostics.js') }}"></script>
        <script src="{{ url(mix('js/dist/Chart.min.js')) }}"></script>
        
<script nonce="{{ csrf_token() }}">
    // ---------------------------
    // - CHART FILTER TOGGLE SYSTEM -
    // ---------------------------
    class ChartFilterToggle {
        constructor(chartId, filterId) {
            this.chartId = chartId;
            this.filterId = filterId;
            this.currentMode = 'category'; // 'category' ou 'status'
            this.chart = null;
            this.filterData = {
                categories: [],
                statuses: []
            };
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.loadFilterData();
        }
        
        bindEvents() {
            const toggleBtn = document.getElementById('filterToggleBtn');
            const filterSelect = document.getElementById(this.filterId);
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => this.toggleMode());
            }
            
            if (filterSelect) {
                filterSelect.addEventListener('change', (e) => this.handleFilterChange(e.target.value));
            }
        }
        
        toggleMode() {
            this.currentMode = this.currentMode === 'category' ? 'status' : 'category';
            this.updateUI();
            this.updateFilterOptions();
            this.reloadChart();
            
            // Adicionar feedback visual
            this.addToggleFeedback();
            
            // Anunciar mudança para leitores de tela
            this.announceToggle();
        }
        
        updateUI() {
            const container = document.getElementById('chartFilterContainer');
            const modeText = document.getElementById('filterModeText');
            const toggleBtn = document.getElementById('filterToggleBtn');
            
            if (container) {
                container.className = `chart-filter ${this.currentMode}-mode`;
            }
            
            if (modeText) {
                if (this.currentMode === 'category') {
                    modeText.textContent = '{{ trans("general.filter_by_category") }}';
                } else {
                    modeText.textContent = '{{ trans("general.filter_by_status") }}';
                }
            }
            
            if (toggleBtn) {
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    // Animação de rotação do ícone
                    icon.style.transform = 'rotate(180deg)';
                    setTimeout(() => {
                        icon.style.transform = 'rotate(0deg)';
                    }, 300);
                }
            }
        }
        
        updateFilterOptions() {
            const filterSelect = document.getElementById(this.filterId);
            if (!filterSelect) return;
            
            // Limpar opções existentes
            filterSelect.innerHTML = '';
            
            if (this.currentMode === 'category') {
                // Mostrar opções de status no filtro lateral
                filterSelect.innerHTML = '<option value="all">{{ trans("general.all_statuses") }}</option>';
                this.filterData.statuses.forEach(status => {
                    const option = document.createElement('option');
                    option.value = status.id;
                    option.textContent = status.name;
                    filterSelect.appendChild(option);
                });
            } else {
                // Mostrar opções de categoria no filtro lateral
                filterSelect.innerHTML = '<option value="all">{{ trans("general.all_categories") }}</option>';
                this.filterData.categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.name;
                    filterSelect.appendChild(option);
                });
            }
        }
        
        loadFilterData() {
            // Carregar categorias
            this.loadCategories();
            // Carregar status
            this.loadStatuses();
        }
        
        loadCategories() {
            const categories = @json($asset_categories ?? []);
            
            if (categories && categories.length > 0) {
                this.filterData.categories = categories;
            } else {
                // Fallback categories
                this.filterData.categories = [
                    {id: 2, name: 'Impressora Térmica'},
                    {id: 3, name: 'Smartphone'},
                    {id: 4, name: 'Notebook'},
                    {id: 5, name: 'Monitor'},
                    {id: 6, name: 'Desktop'},
                    {id: 7, name: 'Impressora'},
                    {id: 8, name: 'Access Point'},
                    {id: 9, name: 'Firewall'},
                    {id: 10, name: 'Nobreak'},
                    {id: 11, name: 'Câmera'},
                    {id: 12, name: 'Servidor'},
                    {id: 22, name: 'Chip Ativos'}
                ];
            }
            
            // Inicializar filtro com categorias
            this.updateFilterOptions();
        }
        
        loadStatuses() {
            // Carregar status via AJAX
            $.ajax({
                type: 'GET',
                url: '{{ route("api.statuslabels.index") }}',
                headers: {
                    "X-Requested-With": 'XMLHttpRequest',
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                success: (response) => {
                    if (response && response.rows) {
                        this.filterData.statuses = response.rows.map(status => ({
                            id: status.id,
                            name: status.name
                        }));
                    }
                },
                error: (error) => {
                    console.warn('Could not load status labels:', error);
                    // Fallback statuses
                    this.filterData.statuses = [
                        {id: 1, name: 'Disponível'},
                        {id: 2, name: 'Em uso'},
                        {id: 3, name: 'Manutenção'},
                        {id: 4, name: 'Perdido'},
                        {id: 5, name: 'Descartado'}
                    ];
                }
            });
        }
        
        handleFilterChange(filterValue) {
            this.reloadChart(filterValue);
        }
        
        reloadChart(filterValue = 'all') {
            const chartContainer = document.getElementById('chartContainer');
            if (chartContainer) {
                chartContainer.classList.add('chart-loading');
            }
            
            // Determinar URL e parâmetros baseados no modo atual
            let url;
            let params = {};
            
            if (this.currentMode === 'category') {
                // Gráfico agrupado por categoria, filtrado por status
                url = '{{ route("api.statuslabels.assets.byname") }}';
                if (filterValue !== 'all') {
                    params.status_id = filterValue;
                }
                params.group_by = 'category';
            } else {
                // Gráfico agrupado por status, filtrado por categoria
                const chartType = '{{ \App\Models\Setting::getSettings()->dash_chart_type ?? "name" }}';
                url = chartType === 'name' 
                    ? '{{ route("api.statuslabels.assets.byname") }}'
                    : '{{ route("api.statuslabels.assets.bytype") }}';
                    
                if (filterValue !== 'all') {
                    params.category_id = filterValue;
                }
                params.group_by = 'status';
            }
            
            $.ajax({
                type: 'GET',
                url: url,
                data: params,
                headers: {
                    "X-Requested-With": 'XMLHttpRequest',
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                success: (data) => {
                    this.updateChart(data);
                    if (chartContainer) {
                        chartContainer.classList.remove('chart-loading');
                    }
                },
                error: (error) => {
                    console.error('Error loading chart data:', error);
                    if (chartContainer) {
                        chartContainer.classList.remove('chart-loading');
                    }
                    this.showErrorMessage('Erro ao carregar dados do gráfico');
                }
            });
        }
        
        updateChart(data) {
            const ctx = document.getElementById(this.chartId);
            if (!ctx) return;
            
            // Destruir gráfico existente
            if (window.currentChart) {
                window.currentChart.destroy();
            }
            
            // Criar novo gráfico
            window.currentChart = new Chart(ctx, {
                type: 'pie',
                data: data,
                options: {
                    legend: {
                        position: 'top',
                        responsive: true,
                        maintainAspectRatio: true,
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                const counts = data.datasets[0].data;
                                const total = counts.reduce((sum, count) => sum + count, 0);
                                const prefix = data.labels[tooltipItem.index] || '';
                                const percentage = Math.round(counts[tooltipItem.index] / total * 100);
                                return `${prefix} ${percentage}%`;
                            }
                        }
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 800,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }
        
        addToggleFeedback() {
            const toggleBtn = document.getElementById('filterToggleBtn');
            const chartContainer = document.getElementById('chartContainer');
            
            if (toggleBtn) {
                // Adicionar classe de pulse
                toggleBtn.classList.add('pulse');
                
                // Animação de escala
                toggleBtn.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    toggleBtn.style.transform = 'scale(1)';
                    toggleBtn.classList.remove('pulse');
                }, 600);
            }
            
            if (chartContainer) {
                // Adicionar classe de atualização
                chartContainer.classList.add('updating');
                setTimeout(() => {
                    chartContainer.classList.remove('updating');
                }, 300);
            }
        }
        
        announceToggle() {
            const announcement = document.createElement('div');
            announcement.setAttribute('aria-live', 'polite');
            announcement.setAttribute('aria-atomic', 'true');
            announcement.className = 'sr-only';
            
            const modeText = this.currentMode === 'category' 
                ? '{{ trans("general.filter_by_category") }}'
                : '{{ trans("general.filter_by_status") }}';
            
            announcement.textContent = `Filtro alterado para: ${modeText}`;
            document.body.appendChild(announcement);
            
            setTimeout(() => {
                document.body.removeChild(announcement);
            }, 1000);
        }
        
        showErrorMessage(message) {
            const chartContainer = document.getElementById('chartContainer');
            if (chartContainer) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-warning text-center';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
                
                chartContainer.innerHTML = '';
                chartContainer.appendChild(errorDiv);
            }
        }
    }
    
    // ---------------------------
    // - ASSET STATUS CHART -
    // ---------------------------
      var pieChartCanvas = $("#statusPieChart").get(0).getContext("2d");
      var pieChart = new Chart(pieChartCanvas);
      var ctx = document.getElementById("statusPieChart");
      var currentChart = null;
      
      var pieOptions = {
              legend: {
                  position: 'top',
                  responsive: true,
                  maintainAspectRatio: true,
              },
              tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        counts = data.datasets[0].data;
                        total = 0;
                        for(var i in counts) {
                            total += counts[i];
                        }
                        prefix = data.labels[tooltipItem.index] || '';
                        return prefix+" "+Math.round(counts[tooltipItem.index]/total*100)+"%";
                    }
                }
              }
          };

      // Initialize Chart Filter Toggle System
      var chartFilterToggle = new ChartFilterToggle('statusPieChart', 'categoryFilter');
      
      // Function to load chart data (mantida para compatibilidade)
      function loadStatusChart(categoryId = 'all') {
          // Usar o sistema de toggle se disponível
          if (chartFilterToggle) {
              chartFilterToggle.reloadChart(categoryId);
              return;
          }
          
          // Fallback para o sistema antigo
          var url = '{{ (\App\Models\Setting::getSettings()->dash_chart_type == 'name') ? route('api.statuslabels.assets.byname') : route('api.statuslabels.assets.bytype') }}';
          
          $.ajax({
              type: 'GET',
              url: url,
              data: {
                  category_id: categoryId
              },
              headers: {
                  "X-Requested-With": 'XMLHttpRequest',
                  "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
              },
              dataType: 'json',
              success: function (data) {
                  // Destroy existing chart if it exists
                  if (currentChart) {
                      currentChart.destroy();
                  }
                  
                  currentChart = new Chart(ctx,{
                      type   : 'pie',
                      data   : data,
                      options: pieOptions
                  });
              },
              error: function (data) {
                  console.error('Error loading chart data:', data);
              },
          });
      }

      // Load initial chart
      loadStatusChart();

      var last = document.getElementById('statusPieChart').clientWidth;
      addEventListener('resize', function() {
          var current = document.getElementById('statusPieChart').clientWidth;
          if (current != last) location.reload();
          last = current;
      });

      // Modern collapse functionality
      $(document).ready(function() {
          $('.collapse-btn').on('click', function() {
              var targetId = $(this).data('target');
              var targetElement = $('#' + targetId);
              var card = targetElement.closest('.modern-card');
              var button = $(this);
              var icon = button.find('.collapse-icon');
              
              // Add smooth transition class
              card.addClass('collapsing');
              
              if (card.hasClass('collapsed')) {
                  // Expand
                  card.removeClass('collapsed');
                  button.removeClass('collapsed');
                  icon.removeClass('fa-plus').addClass('fa-minus');
                  
                  targetElement.slideDown(300, function() {
                      card.removeClass('collapsing');
                      card.css('height', 'auto');
                  });
              } else {
                  // Collapse
                  button.addClass('collapsed');
                  icon.removeClass('fa-minus').addClass('fa-plus');
                  
                  targetElement.slideUp(300, function() {
                      card.addClass('collapsed');
                      card.removeClass('collapsing');
                  });
              }
          });
          
          // Initialize all cards as expanded
          $('.modern-card').each(function() {
              $(this).removeClass('collapsed');
          });

          // Dashboard Tabs Functionality
          $('.dashboard-tab').on('click', function() {
              var targetDashboard = $(this).data('dashboard');
              
              // Update active tab
              $('.dashboard-tab').removeClass('active');
              $(this).addClass('active');
              
              // Update active content
              $('.dashboard-content').removeClass('active');
              $('#' + targetDashboard).addClass('active');
              
              // Optional: Add analytics tracking here
              console.log('Switched to dashboard:', targetDashboard);
          });
          
          // Dashboard Icon Navigation Functionality
          function switchDashboard(targetButton, targetDashboard) {
              // Update active icon with smooth transition
              $('.icon-nav-btn').removeClass('active').attr('aria-selected', 'false').attr('tabindex', '-1');
              $(targetButton).addClass('active').attr('aria-selected', 'true').attr('tabindex', '0');
              
              // Update hidden tabs for consistency (manter funcionalidade existente)
              $('.dashboard-tab').removeClass('active');
              $('.dashboard-tab[data-dashboard="' + targetDashboard + '"]').addClass('active');
              
              // Update active content with fade effect
              $('.dashboard-content').removeClass('active');
              $('#' + targetDashboard).addClass('active');
              
              // Trigger any chart reloads or data refreshes if needed
              if (targetDashboard === 'status-overview' && typeof loadStatusChart === 'function') {
                  setTimeout(loadStatusChart, 100);
              }
              
              // Store user preference in localStorage
              try {
                  localStorage.setItem('dashboard_active_view', targetDashboard);
              } catch (e) {
                  console.warn('Could not save dashboard preference:', e);
              }
              
              // Analytics tracking
              console.log('Dashboard switched to:', targetDashboard);
              
              // Announce change to screen readers
              var announcement = '';
              switch(targetDashboard) {
                  case 'status-overview':
                      announcement = '{{ trans("general.status_overview") }} {{ trans("general.selected") }}';
                      break;
                  case 'asset-trends':
                      announcement = '{{ trans("general.maintenances") }} {{ trans("general.selected") }}';
                      break;
                  case 'performance-metrics':
                      announcement = '{{ trans("general.performance_metrics") }} {{ trans("general.selected") }}';
                      break;
              }
              
              if (announcement) {
                  announceToScreenReader(announcement);
              }
          }
          
          // Screen reader announcement function
          function announceToScreenReader(message) {
              var announcement = $('<div>', {
                  'aria-live': 'polite',
                  'aria-atomic': 'true',
                  'class': 'sr-only',
                  'text': message
              });
              
              $('body').append(announcement);
              setTimeout(function() {
                  announcement.remove();
              }, 1000);
          }
          
          // Initialize tooltips for navigation icons
          function initializeTooltips() {
              $('.icon-nav-btn[data-tooltip="true"]').each(function() {
                  var $btn = $(this);
                  var title = $btn.attr('title');
                  
                  // Remove default title to prevent browser tooltip
                  $btn.removeAttr('title').attr('data-original-title', title);
                  
                  // Add hover events for custom tooltip
                  $btn.on('mouseenter focus', function() {
                      showTooltip($btn, title);
                  }).on('mouseleave blur', function() {
                      hideTooltip($btn);
                  });
              });
          }
          
          // Custom tooltip functions
          function showTooltip($element, text) {
              var tooltip = $('<div class="custom-tooltip">' + text + '</div>');
              $('body').append(tooltip);
              
              var offset = $element.offset();
              var elementWidth = $element.outerWidth();
              var tooltipWidth = tooltip.outerWidth();
              
              tooltip.css({
                  position: 'absolute',
                  top: offset.top - tooltip.outerHeight() - 8,
                  left: offset.left + (elementWidth / 2) - (tooltipWidth / 2),
                  background: 'rgba(0, 0, 0, 0.8)',
                  color: 'white',
                  padding: '6px 10px',
                  borderRadius: '4px',
                  fontSize: '12px',
                  whiteSpace: 'nowrap',
                  zIndex: 1000,
                  opacity: 0
              }).animate({ opacity: 1 }, 200);
              
              $element.data('tooltip', tooltip);
          }
          
          function hideTooltip($element) {
              var tooltip = $element.data('tooltip');
              if (tooltip) {
                  tooltip.animate({ opacity: 0 }, 200, function() {
                      tooltip.remove();
                  });
                  $element.removeData('tooltip');
              }
          }
          
          // Click handler for icon navigation
          $('.icon-nav-btn').on('click', function(e) {
              e.preventDefault();
              var targetDashboard = $(this).data('dashboard');
              if (targetDashboard) {
                  switchDashboard(this, targetDashboard);
              }
          });
          
          // Enhanced keyboard navigation support
          $('.icon-nav-btn').on('keydown', function(e) {
              var $current = $(this);
              var $buttons = $('.icon-nav-btn');
              var currentIndex = $buttons.index($current);
              var $target;
              
              switch(e.which) {
                  case 37: // Left arrow
                      e.preventDefault();
                      $target = currentIndex > 0 ? $buttons.eq(currentIndex - 1) : $buttons.last();
                      $target.focus();
                      break;
                  case 38: // Up arrow
                      e.preventDefault();
                      $target = currentIndex > 0 ? $buttons.eq(currentIndex - 1) : $buttons.last();
                      $target.focus();
                      break;
                  case 39: // Right arrow
                      e.preventDefault();
                      $target = currentIndex < $buttons.length - 1 ? $buttons.eq(currentIndex + 1) : $buttons.first();
                      $target.focus();
                      break;
                  case 40: // Down arrow
                      e.preventDefault();
                      $target = currentIndex < $buttons.length - 1 ? $buttons.eq(currentIndex + 1) : $buttons.first();
                      $target.focus();
                      break;
                  case 13: // Enter
                  case 32: // Space
                      e.preventDefault();
                      var targetDashboard = $current.data('dashboard');
                      if (targetDashboard) {
                          switchDashboard($current[0], targetDashboard);
                      }
                      break;
                  case 27: // Escape
                      e.preventDefault();
                      $current.blur();
                      break;
                  case 36: // Home
                      e.preventDefault();
                      $buttons.first().focus();
                      break;
                  case 35: // End
                      e.preventDefault();
                      $buttons.last().focus();
                      break;
              }
          });
          
          // Restore user's last selected dashboard view
          function restoreUserPreference() {
              try {
                  var savedView = localStorage.getItem('dashboard_active_view');
                  if (savedView && $('.icon-nav-btn[data-dashboard="' + savedView + '"]').length > 0) {
                      var targetButton = $('.icon-nav-btn[data-dashboard="' + savedView + '"]')[0];
                      switchDashboard(targetButton, savedView);
                  }
              } catch (e) {
                  console.warn('Could not restore dashboard preference:', e);
              }
          }
          
          // Initialize everything
          initializeTooltips();
          restoreUserPreference();
          
          // Handle window resize for tooltip repositioning
          $(window).on('resize', function() {
              $('.custom-tooltip').remove();
              $('.icon-nav-btn').removeData('tooltip');
          });
          
          // Touch device support
          if ('ontouchstart' in window) {
              $('.icon-nav-btn').on('touchstart', function(e) {
                  var $this = $(this);
                  var title = $this.attr('data-original-title');
                  
                  // Show tooltip on touch
                  if (title && !$this.data('tooltip')) {
                      showTooltip($this, title);
                      
                      // Hide tooltip after 2 seconds
                      setTimeout(function() {
                          hideTooltip($this);
                      }, 2000);
                  }
              });
          }
          
          // Cleanup tooltips when clicking outside
          $(document).on('click', function(e) {
              if (!$(e.target).closest('.icon-nav-btn').length) {
                  $('.custom-tooltip').remove();
                  $('.icon-nav-btn').removeData('tooltip');
              }
          });
          
          // Handle page visibility changes
          $(document).on('visibilitychange', function() {
              if (document.hidden) {
                  $('.custom-tooltip').remove();
                  $('.icon-nav-btn').removeData('tooltip');
              }
          });
          
          // Ensure proper ARIA attributes are set
          $('.icon-nav-btn').each(function() {
              var $btn = $(this);
              if (!$btn.attr('role')) {
                  $btn.attr('role', 'tab');
              }
              if (!$btn.attr('aria-controls')) {
                  var dashboard = $btn.data('dashboard');
                  if (dashboard) {
                      $btn.attr('aria-controls', dashboard);
                  }
              }
          });
          
          // Add role="tabpanel" to dashboard content
          $('.dashboard-content').each(function() {
              var $content = $(this);
              if (!$content.attr('role')) {
                  $content.attr('role', 'tabpanel');
              }
          });
      });
      
      // Inicializa o validador de layout responsivo
      if (typeof ResponsiveLayoutValidator !== 'undefined') {
          window.responsiveLayoutValidator = new ResponsiveLayoutValidator();
          
          // Expõe métodos globais para debug
          window.validateLayout = () => window.responsiveLayoutValidator.forceValidation();
          window.toggleLayoutDebug = () => window.responsiveLayoutValidator.toggleDebugMode();
          
          console.log('Responsive Layout Validator initialized');
          console.log('Use validateLayout() to force validation or toggleLayoutDebug() to enable debug mode');
      }
</script>

{{-- JavaScript para validação de layout responsivo --}}
<script src="{{ asset('js/responsive-layout-validator.js') }}"></script>
@endpush
