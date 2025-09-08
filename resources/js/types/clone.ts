export interface User {
    id: number;
    name: string;
    email: string;
}

export interface CloneIndexProps {
    auth: {
        user: User;
    };
}

export interface CloneFormData {
    sourcePath: string;
    targetPath: string;
    sourceDbHost: string;
    sourceDbName: string;
    sourceDbUser: string;
    sourceDbPass: string;
    targetDbHost: string;
    targetDbName: string;
    targetDbUser: string;
    targetDbPass: string;
    newDomain: string;
    cloneType: 'full' | 'files' | 'database';
}

export interface Step {
    step: number;
    message: string;
}

export interface Status {
    message: string;
    type: 'success' | 'error' | 'info';
}

export interface CloneOption {
    value: 'full' | 'files' | 'database';
    label: string;
    desc: string;
    icon: React.JSX.Element;
}
