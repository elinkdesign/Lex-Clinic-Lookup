import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function Login() {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleWindowsAuth = async () => {
        setIsLoading(true);
        setError(null);

        console.info('[Login] Windows auth button clicked');
        
        try {
            console.info('[Login] Sending request to /windows-auth');
            const response = await fetch('/windows-auth', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
                credentials: 'include',
            });

            console.info('[Login] Response received', {
                status: response.status,
                statusText: response.statusText,
                redirected: response.redirected,
                url: response.url,
                contentType: response.headers.get('content-type'),
            });

            if (!response.ok) {
                const text = await response.text();
                console.error('[Login] Non-OK response body', text);
                throw new Error(`Request failed with status ${response.status}`);
            }
            
            let data: { success?: boolean; redirect?: string; message?: string } = {};

            try {
                data = await response.json();
                console.info('[Login] Parsed JSON payload', data);
            } catch (jsonError) {
                console.error('[Login] Failed to parse JSON response', jsonError);
                throw new Error('Invalid response payload from server.');
            }
            
            if (data.success && data.redirect) {
                console.info('[Login] Authentication success; redirecting', data.redirect);
                window.location.href = data.redirect;
            } else {
                console.warn('[Login] Authentication response missing redirect or success flag', data);
                setError(data.message || 'Authentication failed. Please try again.');
                setIsLoading(false);
            }
        } catch (err) {
            console.error('[Login] Authentication request threw an error', err);
            setError('An error occurred. Please make sure you are on the company network and try again.');
            setIsLoading(false);
        }
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
                        {error && (
                            <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                <p className="text-sm text-red-700">{error}</p>
                            </div>
                        )}

                        {/* Windows Auth Button */}
                        <button
                            onClick={handleWindowsAuth}
                            disabled={isLoading}
                            className="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold rounded-lg transition duration-200 flex items-center justify-center gap-2"
                        >
                            {isLoading ? (
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
                                    Sign in with Windows Authentication
                                </>
                            )}
                        </button>

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
