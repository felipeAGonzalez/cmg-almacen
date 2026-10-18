@php
    $institutionName = $institutionName ?? 'Centro Médico Guadalupano';
    $institutionDepartment = $institutionDepartment ?? 'Almacén e inventario clínico';
    $logoFile = $logoFile ?? 'images/print/logo.png';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $institutionName }}</title>
    <style>
        :root { --ink: #172b4d; --line: #728096; --muted: #526174; --accent: #174f7a; --paper: #fff; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f5; color: var(--ink); font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
        .print-toolbar { width: 210mm; max-width: calc(100% - 24px); margin: 18px auto 10px; display: flex; justify-content: space-between; gap: 8px; }
        .button { border: 1px solid #9aa5b1; border-radius: 5px; padding: 9px 14px; background: #fff; color: #172b4d; text-decoration: none; cursor: pointer; font: inherit; }
        .button-primary { color: #fff; border-color: var(--accent); background: var(--accent); }
        .sheet { width: 210mm; min-height: 277mm; margin: 0 auto 18px; padding: 9mm 11mm; background: var(--paper); box-shadow: 0 3px 18px rgba(15, 23, 42, .14); overflow: hidden; }
        .letterhead { display: grid; grid-template-columns: 1fr 31mm; min-height: 24mm; gap: 8mm; align-items: center; padding-bottom: 3mm; border-bottom: 2px solid var(--ink); }
        .institution { font-size: 19px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; }
        .department { color: var(--muted); margin-top: 3px; font-size: 10px; letter-spacing: .8px; text-transform: uppercase; }
        .logo, .logo-placeholder { width: 31mm; height: 20mm; object-fit: contain; justify-self: end; }
        .logo-placeholder { border: 1px dashed #8e99a8; display: grid; place-items: center; color: #6b7788; font-size: 8px; text-transform: uppercase; text-align: center; }
        h1 { margin: 4mm 0 1mm; font-size: 16px; text-align: center; text-transform: uppercase; }
        .folio { text-align: center; color: var(--muted); margin-bottom: 4mm; }
        .meta { display: grid; grid-template-columns: repeat(4, 1fr); border: 1px solid var(--line); border-bottom: 0; }
        .meta-item { min-height: 13mm; padding: 2mm 2.5mm; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .meta-item:nth-child(4n) { border-right: 0; }
        .meta-item-wide { grid-column: span 2; }
        .label { display: block; color: var(--muted); font-size: 8px; font-weight: 700; margin-bottom: 1mm; text-transform: uppercase; }
        .value { font-weight: 700; }
        .section-title { margin: 4mm 0 1.5mm; font-size: 11px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid var(--line); padding: 1.6mm 2mm; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #edf2f7; font-size: 8px; text-transform: uppercase; text-align: left; }
        .number { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .notes { border: 1px solid var(--line); min-height: 12mm; padding: 2mm; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 18mm; margin-top: 13mm; }
        .signature { border-top: 1px solid var(--ink); padding-top: 1.5mm; text-align: center; }
        .print-note { margin-top: 3mm; color: var(--muted); font-size: 8px; text-align: right; }
        .density-compact { font-size: 9.5px; }
        .density-compact .meta-item { min-height: 10mm; padding: 1.5mm 2mm; }
        .density-compact th, .density-compact td { padding: 1mm 1.5mm; }
        .density-compact .section-title { margin-top: 2.5mm; }
        .density-compact .signatures { margin-top: 8mm; }
        .density-dense { font-size: 8px; }
        .density-dense .sheet { padding-top: 6mm; padding-bottom: 6mm; }
        .density-dense .letterhead { min-height: 18mm; }
        .density-dense h1 { margin: 2mm 0 .5mm; font-size: 13px; }
        .density-dense .folio { margin-bottom: 2mm; }
        .density-dense .meta-item { min-height: 8mm; padding: 1mm 1.5mm; }
        .density-dense th, .density-dense td { padding: .65mm 1mm; font-size: 7px; }
        .density-dense .section-title { margin: 1.8mm 0 .8mm; font-size: 9px; }
        .density-dense .notes { min-height: 7mm; }
        .density-dense .signatures { margin-top: 6mm; }
        @page { size: A4 portrait; margin: 0; }
        @media print {
            body { background: #fff; }
            .print-toolbar { display: none !important; }
            .sheet { width: 210mm; min-height: 297mm; max-height: 297mm; margin: 0; box-shadow: none; page-break-after: avoid; zoom: var(--print-scale, 1); }
        }
        @media (max-width: 800px) {
            .sheet { width: 100%; min-height: auto; padding: 18px; }
            .meta { grid-template-columns: repeat(2, 1fr); }
            .meta-item:nth-child(4n) { border-right: 1px solid var(--line); }
            .meta-item:nth-child(2n) { border-right: 0; }
        }
    </style>
</head>
@php
    $rowCount = ($summaryRows ?? 0) + ($detailRowsCount ?? 0);
    $density = $rowCount > 24 ? 'density-dense' : ($rowCount > 12 ? 'density-compact' : 'density-normal');
    $logoExists = file_exists(public_path($logoFile));
@endphp
<body class="{{ $density }}">
    <div class="print-toolbar">
        <a class="button" href="{{ $backUrl }}">Volver al vale</a>
        <button class="button button-primary" type="button" onclick="window.print()">Imprimir vale</button>
    </div>
    <main class="sheet">
        <header class="letterhead">
            <div>
                <div class="institution">{{ $institutionName }}</div>
                <div class="department">{{ $institutionDepartment }}</div>
            </div>
            @if($logoExists)
                <img class="logo" src="/{{ $logoFile }}" alt="Logotipo de {{ $institutionName }}">
            @else
                <div class="logo-placeholder" aria-label="Espacio reservado para el logotipo">Espacio para<br>logo</div>
            @endif
        </header>
        @yield('document')
    </main>
    <script>
        (() => {
            const fitPrintedSheet = () => {
                const sheet = document.querySelector('.sheet');
                sheet.style.removeProperty('--print-scale');
                const availableHeight = sheet.clientHeight;
                const requiredHeight = sheet.scrollHeight;
                const scale = requiredHeight > availableHeight
                    ? Math.max(0.55, availableHeight / requiredHeight)
                    : 1;

                sheet.style.setProperty('--print-scale', scale.toFixed(3));
            };

            window.addEventListener('beforeprint', fitPrintedSheet);
            window.addEventListener('load', fitPrintedSheet);
        })();
    </script>
</body>
</html>
