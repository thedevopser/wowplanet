import { describe, it, expect } from 'vitest';
import { h } from 'vue';
import { mount } from '@vue/test-utils';
import CatalogTable from './CatalogTable.vue';

const columns = [
    { key: 'icon', label: 'Icône', visuallyHidden: true },
    { key: 'name', label: 'Nom' },
    { key: 'source', label: 'Source', hideBelow: 'sm' },
    { key: 'points', label: 'Points', align: 'end' },
];

const rows = [
    { id: 1, name: 'Proto-drake', source: 'Haut-fait', points: 10 },
    { id: 2, name: 'Raptor', source: 'Vendeur', points: 0 },
];

const mountTable = (props = {}, slots = {}) => mount(CatalogTable, {
    props: { caption: 'Liste des montures', columns, rows, ...props },
    slots,
});

describe('CatalogTable', () => {
    it('captions the table for assistive technologies', () => {
        const wrapper = mountTable();

        expect(wrapper.find('caption').text()).toBe('Liste des montures');
        expect(wrapper.find('caption').classes()).toContain('sr-only');
    });

    it('scopes every header to its column, even a visually hidden one', () => {
        const wrapper = mountTable();
        const headers = wrapper.findAll('thead th');

        expect(headers.map((header) => header.attributes('scope'))).toEqual(['col', 'col', 'col', 'col']);
        expect(headers[0].find('.sr-only').text()).toBe('Icône');
    });

    it('prints the value of each cell by default', () => {
        const wrapper = mountTable();

        expect(wrapper.findAll('tbody tr')[1].findAll('td').map((cell) => cell.text())).toEqual(['', 'Raptor', 'Vendeur', '0']);
    });

    it('lets the page draw a cell through a slot', () => {
        const wrapper = mountTable({}, { 'cell-name': ({ row }) => h('a', { href: `/monture/${row.id}` }, row.name) });

        expect(wrapper.find('tbody a').attributes('href')).toBe('/monture/1');
    });

    it('hides a secondary column below its breakpoint, in the header and the body', () => {
        const wrapper = mountTable();

        expect(wrapper.findAll('thead th')[2].classes()).toEqual(expect.arrayContaining(['hidden', 'sm:table-cell']));
        expect(wrapper.find('tbody tr').findAll('td')[2].classes()).toEqual(expect.arrayContaining(['hidden', 'sm:table-cell']));
    });

    it('aligns numbers to the end', () => {
        const wrapper = mountTable();

        expect(wrapper.find('tbody tr').findAll('td')[3].classes()).toContain('text-right');
    });

    it('marks itself busy while fresh rows are on their way', () => {
        const idle = mountTable();
        const busy = mountTable({ busy: true });

        expect(idle.find('table').attributes('aria-busy')).toBe('false');
        expect(busy.find('table').attributes('aria-busy')).toBe('true');
    });

    it('passes extra attributes to each row', () => {
        const wrapper = mountTable({ rowAttrs: (row) => ({ 'data-testid': `row-${row.id}` }) });

        expect(wrapper.find('[data-testid="row-2"]').text()).toContain('Raptor');
    });
});
