{{-- Chip-Bedienelemente der Terminals (Chip-Menü, „Chip auflegen", COM-Leser verbinden) sind
     aus und nur an, wenn die Adresse mit ?chip=an geöffnet wurde (z. B. in der Kiosk-
     Verknüpfung). Gemerkt wird das nur für dieses Browserfenster (sessionStorage), damit
     Seitenwechsel und Neuladen es nicht verlieren – nach einem Neustart ohne ?chip=an ist es
     wieder aus. Die Leser selbst (Tastatur-Leser, bereits freigegebener COM-Leser) arbeiten
     unabhängig davon immer. --}}
<script>
    window.kantineChipBedienung = function () {
        const p = new URLSearchParams(location.search);
        const wunsch = p.get('chip');
        if (wunsch !== null) {
            try {
                if (wunsch === 'an') sessionStorage.setItem('kantine.chipBedienung', 'an');
                else sessionStorage.removeItem('kantine.chipBedienung');
            } catch (e) {}
            p.delete('chip');
            history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : '') + location.hash);
        }
        try { return sessionStorage.getItem('kantine.chipBedienung') === 'an'; } catch (e) { return wunsch === 'an'; }
    };
</script>
