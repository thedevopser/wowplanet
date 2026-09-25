import { describe, it, expect, afterEach } from 'vitest';
import { expectNoAxeViolations } from './axe';

afterEach(() => {
    document.body.innerHTML = '';
});

describe('expectNoAxeViolations', () => {
    it('fails on a button without a name, and says which one', async () => {
        document.body.innerHTML = '<main><h1>Titre</h1><button id="mute" type="button"></button></main>';

        await expect(expectNoAxeViolations(document.body)).rejects.toThrow('button-name: #mute');
    });

    it('lets an accessible fragment through', async () => {
        document.body.innerHTML = '<h1>Titre</h1><button type="button">Envoyer</button>';

        await expect(expectNoAxeViolations(document.body)).resolves.toBeUndefined();
    });

    it('asks a whole page for its landmarks when told to', async () => {
        document.body.innerHTML = '<h1>Titre</h1><p>Texte hors de tout point de repère</p>';

        await expect(expectNoAxeViolations(document.body, { landmarks: true })).rejects.toThrow('region: h1, p');
    });
});
