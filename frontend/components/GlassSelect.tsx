import Select from 'react-select';

interface GlassSelectProps {
    value: { value: string; label: string } | null;
    onChange: (option: any) => void;
    options: { value: string; label: string }[];
    placeholder?: string;
    className?: string;
    isSearchable?: boolean;
}

export function GlassSelect({ value, onChange, options, placeholder = "Select...", className = "", isSearchable = true }: GlassSelectProps) {
    return (
        <Select
            value={value}
            onChange={onChange}
            options={options}
            placeholder={placeholder}
            isSearchable={isSearchable}
            className={className}
            classNames={{
                control: ({ isFocused }) =>
                    `flex bg-gray-800/50 border ${isFocused ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-gray-700'
                    } rounded-xl px-2 py-0.5 text-white transition-all shadow-none hover:border-gray-600 cursor-pointer min-h-[44px]`,
                menu: () =>
                    "bg-gray-800 border border-gray-700 rounded-xl mt-2 overflow-hidden shadow-2xl backdrop-blur-xl",
                option: ({ isFocused, isSelected }) =>
                    `px-4 py-2 cursor-pointer transition-colors ${isSelected
                        ? "bg-indigo-500/20 text-indigo-400"
                        : isFocused
                            ? "bg-gray-700/50 text-white"
                            : "text-gray-300 hover:bg-gray-700/50 hover:text-white"
                    }`,
                singleValue: () => "text-white",
                input: () => "text-white",
                placeholder: () => "text-gray-500",
                indicatorSeparator: () => "hidden",
                dropdownIndicator: ({ isFocused }) =>
                    `p-2 transition-colors ${isFocused ? "text-indigo-400" : "text-gray-500"} hover:text-gray-300`,
                clearIndicator: () => "p-2 text-gray-500 hover:text-red-400 transition-colors",
            }}
            styles={{
                control: () => ({}),
                menu: () => ({}),
                option: () => ({}),
                singleValue: () => ({}),
                input: () => ({}),
                placeholder: () => ({}),
                indicatorSeparator: () => ({}),
                dropdownIndicator: () => ({}),
                clearIndicator: () => ({}),
            }}
        />
    );
}

export default GlassSelect;
