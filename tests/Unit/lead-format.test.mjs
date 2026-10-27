import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    categoryColor,
    contactDeadline,
    formatLeadDate,
    formatLeadMoney,
    formatLeadMoneyInput,
    localCivilDate,
    parseLeadMoney,
} from '../../resources/js/lib/lead-format.ts';

for (const [stored, input] of [
    [null, ''],
    ['0.00', '0,00'],
    ['1500.50', '1.500,50'],
    ['9999999999.99', '9.999.999.999,99'],
]) {
    test(`editing money preserves the exact decimal value: ${stored}`, () => {
        assert.equal(formatLeadMoneyInput(stored), input);
        assert.equal(parseLeadMoney(input), stored);
    });
}

for (const [value, expected] of [
    [null, 'Não agendado'],
    ['2026-10-16', 'Hoje'],
    ['2026-10-17', 'Amanhã'],
    ['2026-10-15', 'Ontem'],
    ['2026-10-13', 'Atrasado há 3 dias'],
    ['2026-10-20', 'Faltam 4 dias'],
]) {
    test(`contact deadline: ${value}`, () =>
        assert.equal(contactDeadline(value, '2026-10-16'), expected));
}
test('civil dates survive DST, leap days and years before 100', () => {
    assert.equal(contactDeadline('2024-03-11', '2024-03-09'), 'Faltam 2 dias');
    assert.equal(contactDeadline('2024-11-04', '2024-11-02'), 'Faltam 2 dias');
    assert.equal(contactDeadline('2024-03-01', '2024-02-28'), 'Faltam 2 dias');
    assert.equal(contactDeadline('0001-01-03', '0001-01-01'), 'Faltam 2 dias');
    assert.equal(formatLeadDate('2026-10-16'), '16/10/2026');
});
test('today uses the local calendar rather than the UTC date', () => {
    const original = process.env.TZ;
    try {
        process.env.TZ = 'America/Porto_Velho';
        assert.equal(
            localCivilDate(new Date('2026-10-17T02:00:00Z')),
            '2026-10-16',
        );
        process.env.TZ = 'Pacific/Kiritimati';
        assert.equal(
            localCivilDate(new Date('2026-10-16T12:00:00Z')),
            '2026-10-17',
        );
    } finally {
        if (original === undefined) delete process.env.TZ;
        else process.env.TZ = original;
    }
});
for (const [value, expected] of [
    ['', null],
    ['  ', null],
    ['0', '0.00'],
    ['0,00', '0.00'],
    ['1500,1', '1500.10'],
    ['1.500,01', '1500.01'],
    ['0001,05', '1.05'],
    ['9.999.999.999,99', '9999999999.99'],
    ['-1,00', undefined],
    ['1,005', undefined],
    ['1.23', undefined],
    ['1e2', undefined],
    ['10.000.000.000,00', undefined],
    ['1.23,00', undefined],
    ['R$ 1,00', undefined],
])
    test(`money input: ${JSON.stringify(value)}`, () =>
        assert.equal(parseLeadMoney(value), expected));
test('money display preserves null zero and every cent', () => {
    assert.equal(formatLeadMoney(null), 'Não informado');
    assert.equal(formatLeadMoney('0.00'), 'R$ 0,00');
    assert.equal(formatLeadMoney('1500.01'), 'R$ 1.500,01');
    assert.equal(formatLeadMoney('9999999999.99'), 'R$ 9.999.999.999,99');
});
test('database colors cannot inject CSS', () => {
    assert.equal(categoryColor('#00aAbB'), '#00aAbB');
    assert.equal(
        categoryColor('red; background:url(https://example.test)'),
        '#6d54b5',
    );
});
