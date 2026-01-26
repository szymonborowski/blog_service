<!-- resources/views/login.blade.php -->
@extends('layouts.app')

@section('title', 'Logowanie')

@section('content')
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">Zaloguj się na swoje konto</h2>
                <p class="mt-2 text-center text-sm text-gray-600">Nie masz konta? <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-sky-800">Zarejestruj się</a></p>
            </div>

            <form method="POST" action="{{ url('/login')  }}" class="mt-8 space-y-6 bg-white p-6 rounded-lg shadow">
                @csrf

                <div class="rounded-md shadow-sm -space-y-px">
                    <div class="mb-4">
                        <label for="email" class="sr-only">Email</label>
                        <input id="email" name="email" type="email" autocomplete="email" required
                               class="appearance-none rounded relative block w-full px-3 py-2 border @error('email') border-red-500 @else border-gray-300 @enderror placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-sky-700 focus:border-sky-700 sm:text-sm"
                               placeholder="Email" value="{{ old('email') }}">
                        @error('email')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="sr-only">Hasło</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="appearance-none rounded relative block w-full px-3 py-2 border @error('password') border-red-500 @else border-gray-300 @enderror placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-sky-700 focus:border-sky-700 sm:text-sm"
                               placeholder="Hasło">
                        @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember_me" name="remember" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-sky-700 border-gray-300 rounded" {{ old('remember') ? 'checked' : '' }}>
                        <label for="remember_me" class="ml-2 block text-sm text-gray-900">Zapamiętaj mnie</label>
                    </div>

                    <div class="text-sm">
                        @if (Route::has('password.request'))
                            <a class="font-medium text-indigo-600 hover:text-sky-800" href="{{ route('password.request') }}">Zapomniałeś hasła?</a>
                        @endif
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-sky-800 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">
                        Zaloguj się
                    </button>
                </div>

                @if(session('status'))
                    <p class="text-sm text-sky-600">{{ session('status') }}</p>
                @endif

                <!-- Optional: social login buttons (uncomment and adapt) -->
                {{--
                <div class="pt-4">
                  <div class="text-sm text-center text-gray-500 mb-2">albo zaloguj się przez</div>
                  <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('social.redirect', 'google') }}" class="py-2 px-4 border rounded-md text-sm text-gray-700 hover:bg-gray-50">Google</a>
                    <a href="{{ route('social.redirect', 'github') }}" class="py-2 px-4 border rounded-md text-sm text-gray-700 hover:bg-gray-50">GitHub</a>
                  </div>
                </div>
                --}}

            </form>
        </div>
    </div>
@endsection
