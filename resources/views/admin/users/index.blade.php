@extends('layouts.app', ['title' => 'Usuarios'])

@section('content')
    <section class="panel">
        <h1>Usuarios</h1>
        <p class="hint">
            Roles: <strong>1 = guest</strong>, <strong>2 = user</strong>, <strong>3 = admin</strong>.
            Los roles son solo de consulta.
        </p>
    </section>

    <section class="panel">
        <h2>Listado de usuarios</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Rol actual</th>
                    <th>MFA</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->email ?? 'N/A' }}</td>
                        <td>
                            <span class="badge">{{ $user->role?->name ?? 'sin rol' }}</span>
                        </td>
                        <td>{{ $user->role?->required_mfa_level ?? '-' }}</td>
                        <td>
                            @if ($user->deleted_at)
                                <span class="warning">eliminado</span>
                            @else
                                {{ $user->status }}
                            @endif
                        </td>
                        <td><a href="{{ route('admin.users.show', $user) }}">Detalle</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No hay usuarios registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $users->links() }}
    </section>
@endsection
