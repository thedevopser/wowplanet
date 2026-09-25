import { describe, it, expect } from 'vitest';
import { healthStatus, importStatus, UnknownAdminStatusError } from './adminStatus';
import { BADGE_SEMANTIC_TONES } from '../components/ui/Badge.vue';

describe('healthStatus', () => {
    it.each([
        ['ok', 'OK', 'success'],
        ['warning', 'Attention', 'warning'],
        ['critical', 'Anomalie', 'danger'],
        ['unavailable', 'Injoignable', 'danger'],
    ])('names the %s status in words, in the tone of its gravity', (status, label, tone) => {
        expect(healthStatus(status)).toEqual({ label, tone });
    });

    it('refuses a status the health report does not produce', () => {
        expect(() => healthStatus('mystery')).toThrow(UnknownAdminStatusError);
        expect(() => healthStatus('mystery')).toThrow('Unknown health status: "mystery"');
    });
});

describe('importStatus', () => {
    it.each([
        ['completed', 'Terminé', 'success'],
        ['failed', 'Terminé avec échecs', 'danger'],
        ['cancelled', 'Annulé', 'warning'],
        ['abandoned', 'Abandonné', 'warning'],
        ['running', 'En cours', 'info'],
        ['paused', 'En pause', 'info'],
        ['pending', 'En attente', 'info'],
        ['skipped', 'Déjà à jour', 'neutral'],
    ])('names the %s status in words', (status, label, tone) => {
        expect(importStatus(status)).toEqual({ label, tone });
    });

    it('tells a cancelled or abandoned import apart from a complete one', () => {
        expect(importStatus('cancelled').tone).not.toBe(importStatus('completed').tone);
        expect(importStatus('abandoned').tone).not.toBe(importStatus('completed').tone);
    });

    // An import step the panel does not know yet stays readable rather than breaking the history.
    it('shows a status it does not know under its raw name, in the neutral tone', () => {
        expect(importStatus('mystery')).toEqual({ label: 'mystery', tone: 'neutral' });
    });

    it('only uses the tones of the badge', () => {
        ['completed', 'failed', 'cancelled', 'running', 'skipped'].forEach((status) => {
            expect(Object.keys(BADGE_SEMANTIC_TONES)).toContain(importStatus(status).tone);
        });
    });
});
