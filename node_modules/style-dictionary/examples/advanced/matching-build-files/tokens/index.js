import { readdirSync } from 'node:fs';

const dirs = (p) =>
  readdirSync(p, { withFileTypes: true })
    .filter((d) => d.isDirectory())
    .map((d) => d.name);

export default dirs(import.meta.dirname);
