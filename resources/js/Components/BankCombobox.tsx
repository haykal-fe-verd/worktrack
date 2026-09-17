import { Button } from '@/Components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { INDONESIAN_BANKS } from '@/data/indonesian-banks';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';

export default function BankCombobox({
    value,
    onChange,
    id,
    placeholder = 'Pilih atau cari nama bank...',
}: {
    value: string;
    onChange: (value: string) => void;
    id?: string;
    placeholder?: string;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const trimmedSearch = search.trim();
    const hasExactMatch = INDONESIAN_BANKS.some(
        (bank) => bank.toLowerCase() === trimmedSearch.toLowerCase(),
    );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="w-full justify-between font-normal"
                >
                    <span
                        className={cn(
                            'truncate',
                            !value && 'text-muted-foreground',
                        )}
                    >
                        {value || placeholder}
                    </span>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[--radix-popover-trigger-width] p-0"
                align="start"
            >
                <Command shouldFilter>
                    <CommandInput
                        placeholder="Cari nama bank..."
                        value={search}
                        onValueChange={setSearch}
                    />
                    <CommandList>
                        <CommandEmpty>Bank tidak ditemukan.</CommandEmpty>
                        <CommandGroup>
                            {INDONESIAN_BANKS.map((bank) => (
                                <CommandItem
                                    key={bank}
                                    value={bank}
                                    onSelect={() => {
                                        onChange(bank);
                                        setSearch('');
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 h-4 w-4',
                                            value === bank
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    {bank}
                                </CommandItem>
                            ))}
                            {trimmedSearch && !hasExactMatch && (
                                <CommandItem
                                    key="__custom"
                                    value={trimmedSearch}
                                    onSelect={() => {
                                        onChange(trimmedSearch);
                                        setSearch('');
                                        setOpen(false);
                                    }}
                                >
                                    <Check className="mr-2 h-4 w-4 opacity-0" />
                                    Gunakan &quot;{trimmedSearch}&quot;
                                </CommandItem>
                            )}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
