export default {
	async fetch(request, env, ctx) {
	  const url = new URL(request.url);
	  const pathname = url.pathname;

	  // Handle static assets from Workers directly if they exist
	  if (pathname.startsWith('/assets/') || pathname.endsWith('.css') || pathname.endsWith('.js')) {
		const assetResponse = await env.ASSETS.fetch(request);
		if (assetResponse.status !== 404) {
		  return assetResponse;
		}
	  }

	  // Your custom endpoints
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

	  // Forward all other requests to your Laravel application
	  // Replace YOUR_LARAVEL_HOST with your actual Laravel host
	  try {
		const laravel_host = "https://smartdalib.work";
		return fetch(new Request(new URL(pathname + url.search, laravel_host), request));
	  } catch (error) {
		return new Response(`Error: ${error.message}`, { status: 500 });
	  }
	}
  };
