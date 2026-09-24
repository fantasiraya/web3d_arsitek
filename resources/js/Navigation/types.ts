import type { Component } from 'vue';

export interface NavLinkItem {
    type: 'link';
    id: string;
    label: string;
    href: string;
    icon: Component;
    badge?: string | null;
}

export interface NavGroupItem {
    type: 'group';
    id: string;
    label: string;
    icon: Component;
    items: NavLinkItem[];
}

export type NavItem = NavLinkItem | NavGroupItem;
