<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bestell-Terminal · Schulkantine</title>
    @includeIf('layouts.favicon')
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full items-center justify-center bg-gray-100 p-6 font-sans antialiased text-gray-800">
    <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-8 text-center">
        <x-module-icon name="restaurant" class="text-4xl text-gray-400" />
        <h1 class="mt-4 text-2xl font-semibold text-gray-800">Nur im Schulnetz</h1>
        <p class="mt-2 text-gray-500">Das Bestell-Terminal der Schulkantine ist nur an den Terminals in der Schule erreichbar.</p>
        <p class="mt-4 text-xs text-gray-400">Deine Adresse: {{ $ip }}</p>
    </div>
</body>
</html>
