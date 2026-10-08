{{-- Chip-Bedienelemente der Terminals (Chip-Menü, „Chip auflegen", COM-Leser verbinden) sind
     standardmäßig aus und werden JE GERÄT eingeschaltet: Adresse einmal mit ?chip=an bzw.
     ?chip=aus öffnen, das Gerät merkt es sich (localStorage). Über die IP geht es nicht –
     alle Schulrechner kommen mit derselben Adresse an. Die Leser selbst (Tastatur-Leser,
     bereits freigegebener COM-Leser) arbeiten unabhängig davon immer. --}}
<script>
    window.kantineChipBedienung = function () {
        const p = new URLSearchParams(location.search);
        const wunsch = p.get('chip');
        if (wunsch !== null) {
            try { localStorage.setItem('kantine.chipBedienung', wunsch === 'an' ? 'an' : 'aus'); } catch (e) {}
            p.delete('chip');
            history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : '') + location.hash);
        }
        try { return localStorage.getItem('kantine.chipBedienung') === 'an'; } catch (e) { return false; }
    };
</script>
