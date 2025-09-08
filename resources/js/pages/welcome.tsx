import { dashboard, login } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">
                <header className="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
                    <nav className="flex items-center justify-end gap-4">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <Link
                                href={login()}
                                className="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                            >
                                Log in
                            </Link>
                        )}
                    </nav>
                </header>
                <div className="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
                    <main className="flex w-full max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row">
                        <div className="flex-1 rounded-br-lg rounded-bl-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-tl-lg lg:rounded-br-none lg:p-20 dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                            <h1 className="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC] mb-6">
                                Additional Toolkits to Manage WordPress Sites
                            </h1>

                            <ul className="space-y-4 text-[13px] leading-[20px]">
                                <li className="flex items-start gap-3">
                                    <div className="flex h-6 w-6 items-center justify-center rounded-full bg-green-200 text-green-700 text-sm dark:bg-green-800 dark:text-green-200">
                                        ⏳
                                    </div>
                                    <span className="text-[#1b1b18] dark:text-[#EDEDEC]">Easy website cloning <span className="italic">(In Progress)</span>
                                    </span>
                                </li>

                                <li className="flex items-start gap-3 opacity-70">
                                    <div className="flex h-6 w-6 items-center justify-center rounded-full bg-yellow-200 text-yellow-700 text-sm dark:bg-yellow-900 dark:text-yellow-300">
                                        ⏳
                                    </div>
                                    <span className="text-[#1b1b18] dark:text-[#EDEDEC]">
                                        Easy website backup <span className="italic">(coming soon)</span>
                                    </span>
                                </li>

                                <li className="flex items-start gap-3 opacity-70">
                                    <div className="flex h-6 w-6 items-center justify-center rounded-full bg-yellow-200 text-yellow-700 text-sm dark:bg-yellow-900 dark:text-yellow-300">
                                        ⏳
                                    </div>
                                    <span className="text-[#1b1b18] dark:text-[#EDEDEC]">
                                        Easy website monitoring <span className="italic">(coming soon)</span>
                                    </span>
                                </li>
                            </ul>

                            <p className="mt-6 text-[12px] text-[#55554d] dark:text-[#aaa99f]">No hassle, straight forward.</p>
                        </div>
                        <div className="flex-1 rounded-tr-lg rounded-tl-lg bg-white p-6 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] sm:border-r-none lg:rounded-tr-lg lg:rounded-bl-none lg:rounded-tl-none lg:p-20 dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                            <img src='/logo.png' alt="Logo" className="w-2xl" />
                        </div>
                    </main>
                </div>
                <div className="hidden h-14.5 lg:block"></div>
            </div>
        </>
    );
}
