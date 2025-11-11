import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        password: '',
    });

    const formErrors = errors as Record<string, string | undefined>;
    const generalError = formErrors.auth;

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        post('/windows-auth', {
            preserveScroll: true,
            onError: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Login - NID Lookup" />
            <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
                <div className="w-full max-w-md">
                    {/* Card */}
                    <div className="bg-white rounded-lg shadow-xl p-8">
                        {/* Logo */}
                        <div className="flex justify-center mb-8">
                            <img 
                                src="/Lex-Clinic-logo.webp" 
                                alt="Lexington Clinic Logo"
                                className="h-20 object-contain"
                            />
                        </div>

                        {/* Title */}
                        <h1 className="text-3xl font-bold text-center text-gray-900 mb-2">
                            NID Lookup Tool
                        </h1>
                        <p className="text-center text-gray-600 mb-8">
                            Sign in with your company credentials
                        </p>

                        {/* Error Message */}
                        {generalError && (
                            <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                <p className="text-sm text-red-700">{generalError}</p>
                            </div>
                        )}

                        <form className="space-y-6" onSubmit={handleSubmit}>
                            <div>
                                <label htmlFor="username" className="block text-sm font-medium text-gray-700">
                                    Network Username
                                </label>
                                <input
                                    id="username"
                                    name="username"
                                    value={data.username}
                                    onChange={(event) => setData('username', event.target.value)}
                                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    autoComplete="username"
                                    required
                                />
                                {formErrors.username && (
                                    <p className="mt-1 text-sm text-red-600">{formErrors.username}</p>
                                )}
                            </div>

                            <div>
                                <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                                    Password
                                </label>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                    autoComplete="current-password"
                                    required
                                />
                                {formErrors.password && (
                                    <p className="mt-1 text-sm text-red-600">{formErrors.password}</p>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold rounded-lg transition duration-200 flex items-center justify-center gap-2"
                            >
                                {processing ? (
                                    <>
                                        <svg className="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Signing in...
                                    </>
                                ) : (
                                    <>
                                        <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path>
                                        </svg>
                                        Sign in
                                    </>
                                )}
                            </button>
                        </form>

                        {/* Helper Text */}
                        <p className="text-center text-gray-500 text-sm mt-6">
                            Make sure you are connected to the company network or VPN
                        </p>
                    </div>

                    {/* Footer */}
                    <div className="text-center mt-8 text-gray-600 text-sm">
                        <p>© Lexington Clinic</p>
                    </div>
                </div>
            </div>
        </>
    );
}
