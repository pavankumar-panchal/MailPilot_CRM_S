import Quill, { QuillOptionsStatic } from 'quill';
import { useEffect, useMemo, useRef, useState } from 'react';

import 'quill/dist/quill.snow.css';
import '../../quill.css';

type UploadResponse = {
  success: boolean;
  url: string;
  path: string;
  message?: string;
};

export type RichTextEditorProps = {
  value: string;
  onChange: (value: string) => void;
  modules?: QuillOptionsStatic['modules'];
  formats?: string[];
  placeholder?: string;
  onImageUpload?: (path: string) => void;
  uploadImageUrl: string;
  minHeight?: number;
};

const defaultModules: NonNullable<QuillOptionsStatic['modules']> = {
  toolbar: [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ color: [] }, { background: [] }],
    ['link', 'image'],
    ['clean'],
  ],
};

const defaultFormats = [
  'header',
  'bold',
  'italic',
  'underline',
  'strike',
  'list',
  'bullet',
  'link',
  'color',
  'background',
  'image',
];

function useLatest<T>(value: T) {
  const ref = useRef(value);
  useEffect(() => {
    ref.current = value;
  }, [value]);
  return ref;
}

const MINIMAL_DELTA = '<p><br></p>';

export default function RichTextEditor({
  value,
  onChange,
  modules,
  formats,
  placeholder,
  onImageUpload,
  uploadImageUrl,
  minHeight = 200,
}: RichTextEditorProps) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const quillRef = useRef<Quill | null>(null);
  const toolbarRef = useRef<HTMLElement | null>(null);
  const [isReady, setIsReady] = useState(false);

  const onChangeRef = useLatest(onChange);
  const onImageUploadRef = useLatest(onImageUpload);

  const mergedModules = useMemo(() => modules ?? defaultModules, [modules]);
  const mergedFormats = useMemo(() => formats ?? defaultFormats, [formats]);

  useEffect(() => {
    if (!containerRef.current || quillRef.current) {
      return;
    }

    const container = containerRef.current;

    // Remove any previous toolbar rendered by Quill within the parent container
    const parentToolbar = container.parentElement?.querySelector<HTMLElement>(':scope > .ql-toolbar');
    parentToolbar?.remove();

    const quill = new Quill(container, {
      theme: 'snow',
      modules: mergedModules,
      formats: mergedFormats,
      placeholder,
    });

    quillRef.current = quill;
    setIsReady(true);

    const toolbarModule = quill.getModule('toolbar');
    if (toolbarModule && 'container' in toolbarModule) {
      toolbarRef.current = toolbarModule.container as HTMLElement;
  toolbarModule.addHandler('image', async () => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.click();

        input.onchange = async () => {
          const file = input.files?.[0];
          if (!file) return;

          const selection = quill.getSelection(true);
          const placeholderIndex = selection?.index ?? quill.getLength();
          quill.insertText(placeholderIndex, 'Uploading image…');

          const formData = new FormData();
          formData.append('image', file);

          try {
            const response = await fetch(uploadImageUrl, {
              method: 'POST',
              body: formData,
            });

            const payload = (await response.json()) as UploadResponse;
            quill.deleteText(placeholderIndex, 'Uploading image…'.length);

            if (payload.success) {
              quill.insertEmbed(placeholderIndex, 'image', payload.url);
              quill.setSelection({ index: placeholderIndex + 1, length: 0 });
              onImageUploadRef.current?.(payload.path);
            } else {
              alert(`✗ Upload failed: ${payload.message ?? 'unknown error'}`);
            }
          } catch (error) {
            quill.deleteText(placeholderIndex, 'Uploading image…'.length);
            if (error instanceof Error) {
              alert(`✗ Upload error: ${error.message}`);
            }
          }
        };
      });
    }

    const handleTextChange = () => {
      if (!quillRef.current) return;
      const html = quillRef.current.root.innerHTML;
      onChangeRef.current(html === MINIMAL_DELTA ? '' : html);
    };

    quill.on('text-change', handleTextChange);

    return () => {
      quill.off('text-change', handleTextChange);
      toolbarRef.current?.remove();
      toolbarRef.current = null;
      quillRef.current = null;
      container.innerHTML = '';
      setIsReady(false);
    };
  }, [mergedFormats, mergedModules, placeholder, uploadImageUrl, onChangeRef, onImageUploadRef]);

  useEffect(() => {
    if (!isReady || !quillRef.current) {
      return;
    }

    const quill = quillRef.current;
    const current = quill.root.innerHTML;
    const nextValue = value || '';

    if (current === nextValue || (current === MINIMAL_DELTA && nextValue === '')) {
      return;
    }

    const selection = quill.getSelection();
    const cursor = selection?.index ?? quill.getLength();

  quill.setContents(quill.clipboard.convert(nextValue));
  quill.setSelection({ index: Math.min(cursor, quill.getLength()), length: 0 });
  }, [isReady, value]);

  return (
    <div className="quill-editor-wrapper bg-white rounded-lg border border-gray-300">
      <div ref={containerRef} style={{ minHeight }} />
    </div>
  );
}
