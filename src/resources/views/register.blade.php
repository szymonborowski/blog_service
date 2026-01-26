<!-- resources/views/register.blade.php -->
@extends('layouts.app')

@section('title', 'Rejestracja')

@section('content')
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">Utwórz nowe konto</h2>
                <p class="mt-2 text-center text-sm text-gray-600">Masz już konto? <a href="{{ route('login') }}" class="font-medium text-sky-600 hover:text-sky-500">Zaloguj się</a></p>
            </div>

            <form method="POST" action="{{ url('/register')  }}" class="mt-8 space-y-6 bg-white p-6 rounded-lg shadow">
                @csrf

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="name" class="sr-only">Nazwa użytkownika</label>
                        <input id="name" name="name" type="text" required autocomplete="name"
                               class="appearance-none rounded relative block w-full px-3 py-2 border @error('name') border-red-500 @else border-gray-300 @enderror placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="Nazwa użytkownika" value="{{ old('name') }}">
                        @error('name')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="sr-only">Email</label>
                        <input id="email" name="email" type="email" required autocomplete="email"
                               class="appearance-none rounded relative block w-full px-3 py-2 border @error('email') border-red-500 @else border-gray-300 @enderror placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-sky-700 focus:border-sky-700 sm:text-sm"
                               placeholder="Email" value="{{ old('email') }}">
                        @error('email')
                        <p class="mt-2 text-sm text-sky-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="sr-only">Hasło</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password"
                               class="appearance-none rounded relative block w-full px-3 py-2 border @error('password') border-sky-500 @else border-gray-300 @enderror placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-sky-700 focus:border-sky-700 sm:text-sm"
                               placeholder="Hasło">
                        @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="sr-only">Powtórz hasło</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                               class="appearance-none rounded relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-sky-700 focus:border-sky-700 sm:text-sm"
                               placeholder="Powtórz hasło">
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-sky-800 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">
                        Zarejestruj się
                    </button>
                </div>

                @if(session('status'))
                    <p class="text-sm text-sky-600">{{ session('status') }}</p>
                @endif

            </form>
        </div>
    </div>
@endsection
