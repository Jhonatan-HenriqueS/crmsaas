import assert from 'node:assert/strict';
import { test } from 'node:test';
import { leadTransition } from '../../resources/js/lib/lead-transition.ts';

const ordinary = {
    id: 1,
    name: 'Finalizado',
    color: '#39d6a0',
    is_sale_completed: false,
};
const completed = {
    id: 2,
    name: 'Entrega confirmada',
    color: '#abcdef',
    is_sale_completed: true,
};
for (const { name, previous, selected, expected } of [
    {
        name: 'ordinary creation needs no confirmation',
        previous: null,
        selected: ordinary,
        expected: null,
    },
    {
        name: 'completed creation requires sale consent',
        previous: null,
        selected: completed,
        expected: 'complete',
    },
    {
        name: 'ordinary to completed requires sale consent',
        previous: ordinary,
        selected: completed,
        expected: 'complete',
    },
    {
        name: 'completed to ordinary requires reopening consent',
        previous: completed,
        selected: ordinary,
        expected: 'reopen',
    },
    {
        name: 'remaining completed needs no repeated consent',
        previous: completed,
        selected: completed,
        expected: null,
    },
    {
        name: 'ordinary editing needs no sale consent',
        previous: ordinary,
        selected: ordinary,
        expected: null,
    },
    {
        name: 'missing selection does not complete a sale',
        previous: ordinary,
        selected: null,
        expected: null,
    },
]) {
    test(name, () =>
        assert.equal(leadTransition(previous, selected), expected),
    );
}
