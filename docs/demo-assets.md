# Demo Portrait Assets

The seeded student portraits and upload fixture use local copies of royalty-free Pexels photos. They are included for demo, seed, and automated-test use only.

License basis:
- Pexels License: https://www.pexels.com/license/
- Pexels Help Center: https://help.pexels.com/hc/en-us/articles/360042295174-What-is-the-license-of-the-photos-and-videos-on-Pexels

Downloaded fixtures:
- `portrait-fin-barri.jpg` from https://www.pexels.com/photo/portrait-of-woman-in-t-shirt-17912826/
- `portrait-grant-allen.jpg` from https://www.pexels.com/photo/portrait-photo-of-a-man-19129643/
- `portrait-ansey.jpg` from https://www.pexels.com/photo/portrait-of-a-woman-on-white-background-15716139/
- `portrait-lena-glukhova.jpg` from https://www.pexels.com/photo/portrait-of-woman-on-white-background-10090949/
- `portrait-frank-minjarez.jpg` from https://www.pexels.com/photo/photo-of-man-in-blue-scrub-suit-20355553/

Usage in this project:
- Seeded student records copy approved portrait fixtures into `storage/app/private/uploads/`.
- Registration acceptance tests upload `tests/Support/Data/student-photo.jpg`.
- Real user uploads remain the primary source for non-demo student photos.
