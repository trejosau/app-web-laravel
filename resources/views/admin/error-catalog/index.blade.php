@extends('layouts.app', ['title' => 'Catalogo de errores'])

@section('content')
    <section class="panel">
        <h1>Catalogo de errores</h1>

        <form method="GET" action="{{ route('admin.error-catalog.index') }}">
            <label for="code">Codigo</label>
            <input id="code" name="code" value="{{ $code }}" autocomplete="off">
            <button type="submit">Buscar</button>
        </form>
    </section>

    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Info usuario</th>
                    <th>Info soporte</th>
                    <th>Info desarrollador</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($errors as $error)
                    <tr>
                        <td>{{ $error['code'] }}</td>
                        <td>{{ $error['userInfo'] }}</td>
                        <td>{{ $error['supportInfo'] }}</td>
                        <td>{{ $error['developerInfo'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
