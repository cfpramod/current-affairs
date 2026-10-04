<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FlaskConical, LayoutGrid } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as batches } from '@/routes/batches';
import type { NavItem } from '@/types';

// Shipments and the raw engine pages stay reachable by URL but are out of
// the everyday navigation: batches are what every role decides on.
const mainNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Batches',
        href: batches(),
        icon: FlaskConical,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
