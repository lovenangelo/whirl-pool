import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { CloneFormData, CloneIndexProps, CloneOption, Status, Step } from '@/types/clone';

export default function CloneIndex({ auth }: CloneIndexProps) {
    console.log(auth);
    const [isCloning, setIsCloning] = useState<boolean>(false);
    const [steps, setSteps] = useState<Step[]>([]);
    const [status, setStatus] = useState<Status>({ message: '', type: 'info' });

    const { data, setData, processing } = useForm<CloneFormData>({
        sourcePath: '',
        targetPath: '',
        sourceDbHost: 'localhost',
        sourceDbName: '',
        sourceDbUser: '',
        sourceDbPass: '',
        targetDbHost: 'localhost',
        targetDbName: '',
        targetDbUser: '',
        targetDbPass: '',
        newDomain: '',
        cloneType: 'full'
    });

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>): Promise<void> => {
        e.preventDefault();
        setIsCloning(true);
        setSteps([]);
        setStatus({ message: 'Starting clone process...', type: 'info' });

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content');

            const response = await fetch('/clone', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify(data),
            });

            const result: { steps?: Step[]; status: Status } = await response.json();
            setSteps(result.steps || []);
            setStatus(result.status);
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'An unknown error occurred';
            setStatus({ message: 'An error occurred: ' + errorMessage, type: 'error' });
        } finally {
            setIsCloning(false);
        }
    };

    const getStatusColor = (type: Status['type']): string => {
        switch (type) {
            case 'success': return 'text-green-600 bg-green-50 border-green-200';
            case 'error': return 'text-red-600 bg-red-50 border-red-200';
            default: return 'text-blue-600 bg-blue-50 border-blue-200';
        }
    };

    const cloneOptions: CloneOption[] = [
        { value: 'full', label: 'Full Clone (Files + Database)', desc: 'Complete WordPress site including files and database' },
        { value: 'files', label: 'Files Only', desc: 'Copy WordPress files without database' },
        { value: 'database', label: 'Database Only', desc: 'Clone database without files' }
    ];

    return (

        <>
            <Head title="WordPress Clone" />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <form onSubmit={handleSubmit} className="space-y-6">
                                {/* Clone Type Selection */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Clone Type
                                    </label>
                                    <div className="space-y-2">
                                        {cloneOptions.map((option) => (
                                            <label key={option.value} className="flex items-start space-x-3 p-3 border rounded-lg hover:bg-gray-50">
                                                <input
                                                    type="radio"
                                                    value={option.value}
                                                    checked={data.cloneType === option.value}
                                                    onChange={(e) => setData('cloneType', e.target.value as CloneFormData['cloneType'])}
                                                    className="mt-1 text-blue-600"
                                                />
                                                <div>
                                                    <div className="font-medium text-gray-900">{option.label}</div>
                                                    <div className="text-sm text-gray-500">{option.desc}</div>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                {/* Source Configuration */}
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Source Configuration</h3>

                                    {(data.cloneType === 'full' || data.cloneType === 'files') && (
                                        <div className="mb-4">
                                            <label className="block text-sm font-medium text-gray-700">Source Path</label>
                                            <input
                                                type="text"
                                                value={data.sourcePath}
                                                onChange={(e) => setData('sourcePath', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="/path/to/wordpress"
                                                required
                                            />
                                        </div>
                                    )}

                                    {(data.cloneType === 'full' || data.cloneType === 'database') && (
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Database Host</label>
                                                <input
                                                    type="text"
                                                    value={data.sourceDbHost}
                                                    onChange={(e) => setData('sourceDbHost', e.target.value)}
                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Database Name</label>
                                                <input
                                                    type="text"
                                                    value={data.sourceDbName}
                                                    onChange={(e) => setData('sourceDbName', e.target.value)}
                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Database User</label>
                                                <input
                                                    type="text"
                                                    value={data.sourceDbUser}
                                                    onChange={(e) => setData('sourceDbUser', e.target.value)}
                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Database Password</label>
                                                <input
                                                    type="password"
                                                    value={data.sourceDbPass}
                                                    onChange={(e) => setData('sourceDbPass', e.target.value)}
                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>

                                {/* Target Configuration */}
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Target Configuration</h3>

                                    {(data.cloneType === 'full' || data.cloneType === 'files') && (
                                        <div className="mb-4">
                                            <label className="block text-sm font-medium text-gray-700">Target Path</label>
                                            <input
                                                type="text"
                                                value={data.targetPath}
                                                onChange={(e) => setData('targetPath', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="/path/to/new-wordpress"
                                                required
                                            />
                                        </div>
                                    )}

                                    {(data.cloneType === 'full' || data.cloneType === 'database') && (
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Database Host</label>
                                                <input
                                                    type="text"
                                                    value={data.targetDbHost}
                                                    onChange={(e) => setData('targetDbHost', e.target.value)}
                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Database Name</label>
                                                <input
                                                    type="text"
                                                    value={data.targetDbName}
                                                    onChange={(e) => setData('targetDbName', e.target.value)}
                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Database User</label>
                                                <input
                                                    type="text"
                                                    value={data.targetDbUser}
                                                    onChange={(e) => setData('targetDbUser', e.target.value)}
                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Database Password</label>
                                                <input
                                                    type="password"
                                                    value={data.targetDbPass}
                                                    onChange={(e) => setData('targetDbPass', e.target.value)}
                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {data.cloneType === 'full' && (
                                        <div className="mt-4">
                                            <label className="block text-sm font-medium text-gray-700">New Domain (Optional)</label>
                                            <input
                                                type="url"
                                                value={data.newDomain}
                                                onChange={(e) => setData('newDomain', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="https://new-domain.com"
                                            />
                                            <p className="mt-1 text-sm text-gray-500">Leave empty to keep original domain URLs</p>
                                        </div>
                                    )}
                                </div>

                                {/* Submit Button */}
                                <div>
                                    <button
                                        type="submit"
                                        disabled={isCloning || processing}
                                        className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {isCloning ? (
                                            <>
                                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Cloning...
                                            </>
                                        ) : (
                                            'Start Clone'
                                        )}
                                    </button>
                                </div>
                            </form>

                            {/* Status Display */}
                            {status.message && (
                                <div className={`mt-6 p-4 border rounded-lg ${getStatusColor(status.type)}`}>
                                    <div className="font-medium">{status.message}</div>
                                </div>
                            )}

                            {/* Progress Steps */}
                            {steps.length > 0 && (
                                <div className="mt-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Progress</h3>
                                    <div className="space-y-2">
                                        {steps.map((step: Step, index: number) => (
                                            <div key={index} className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                                <div className="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <span className="text-xs font-medium text-blue-600">{step.step}</span>
                                                </div>
                                                <div className="text-sm text-gray-700">{step.message}</div>
                                                <div className="flex-shrink-0">
                                                    <svg className="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
