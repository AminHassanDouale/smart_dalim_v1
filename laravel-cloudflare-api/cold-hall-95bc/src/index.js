export default {
	async fetch(request, env, ctx) {
	  const url = new URL(request.url);
	  const pathname = url.pathname;

	  // Serve static assets directly from Workers
	  if (pathname.endsWith('.css') || pathname.endsWith('.js') || pathname.endsWith('.jpg') || pathname.endsWith('.png')) {
		const assetResponse = await env.ASSETS.fetch(request);
		if (assetResponse.status !== 404) {
		  return assetResponse;
		}
	  }

	  // Custom endpoints
	  if (pathname === '/message') {
		return new Response('👋 Hello from your Worker!', {
		  headers: { 'Content-Type': 'text/plain' },
		});
	  }

	  if (pathname === '/random') {
		const uuid = crypto.randomUUID();
		return new Response(uuid, {
		  headers: { 'Content-Type': 'text/plain' },
		});
	  }

	  // Forward everything else to your PHP server
	  // Replace with your actual PHP server URL (e.g., a VPS or shared hosting)
	  return fetch('https://your-php-server.example.com' + pathname + url.search, {
		method: request.method,
		headers: request.headers,
		body: request.body
	  });
	}
  };
